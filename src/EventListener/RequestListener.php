<?php
/*
 * This file is part of the KleijnWeb\RestETagBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KleijnWeb\RestETagBundle\EventListener;

use Doctrine\Common\Cache\Cache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class RequestListener
{
    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var bool
     */
    private $concurrencyControl;

    /**
     * @param Cache $cache
     * @param bool  $concurrencyControl
     */
    public function __construct(Cache $cache, $concurrencyControl = true)
    {
        $this->cache = $cache;
        $this->concurrencyControl = $concurrencyControl;
    }

    /**
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        if ($response = $this->createResponse($event->getRequest())) {
            $event->setResponse($response);
        }
    }

    /**
     * @param Request $request
     *
     * @return bool
     */
    public static function isModifyingRequest(Request $request)
    {
        $method = strtoupper($request->getMethod());

        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    /**
     * @param Request $request
     *
     * @return null|Response
     */
    private function createResponse(Request $request)
    {
        if (!$version = $this->cache->fetch($request->getPathInfo())) {
            return null;
        }
        $method = strtoupper($request->getMethod());

        if ($method === 'GET') {
            $ifNoneMatch = $request->headers->get('If-None-Match');
            if ($ifNoneMatch && $version === $ifNoneMatch) {
                return new Response('', Response::HTTP_NOT_MODIFIED);
            }
        } elseif ($this->concurrencyControl && self::isModifyingRequest($request)) {
            $ifMatch = $request->headers->get('If-Match');
            if (!$ifMatch) {
                return new Response('', Response::HTTP_PRECONDITION_REQUIRED);
            }
            if ($version !== $ifMatch) {
                return new Response('', Response::HTTP_PRECONDITION_FAILED);
            }
        }

        return null;
    }
}
