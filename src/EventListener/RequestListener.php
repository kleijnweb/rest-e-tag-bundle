<?php declare(strict_types = 1);
/*
 * This file is part of the KleijnWeb\RestETagBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KleijnWeb\RestETagBundle\EventListener;

use KleijnWeb\RestETagBundle\Version\VersionStore;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class RequestListener
{
    /**
     * @var VersionStore
     */
    private $store;

    /**
     * @var bool
     */
    private $concurrencyControl;

    /**
     * @param VersionStore $store
     * @param bool         $concurrencyControl
     */
    public function __construct(VersionStore $store, bool $concurrencyControl = true)
    {
        $this->store = $store;
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
    public static function isModifyingMethodRequest(Request $request)
    {
        return self::requestMethodInHaystack($request, ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    /**
     * @param Request $request
     *
     * @return bool
     */
    public static function isIgnoreMethodRequest(Request $request)
    {
        return self::requestMethodInHaystack($request, ['OPTIONS', 'HEAD']);
    }

    /**
     * @param Request $request
     *
     * @return bool
     */
    public static function isSupportedMethodRequest(Request $request)
    {
        return self::requestMethodInHaystack($request, ['GET', 'OPTIONS', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE']);
    }


    /**
     * @param Request $request
     * @param array   $haystack
     *
     * @return bool
     */
    private static function requestMethodInHaystack(Request $request, array $haystack)
    {
        return in_array(strtoupper($request->getMethod()), $haystack);
    }

    /**
     * @param Request $request
     *
     * @return null|Response
     */
    private function createResponse(Request $request)
    {
        if (!self::isSupportedMethodRequest($request)) {
            return new Response('', Response::HTTP_METHOD_NOT_ALLOWED);
        }

        if (!$version = $this->store->fetch($request)) {
            return null;
        }
        $method = strtoupper($request->getMethod());

        if ($method === 'GET') {
            $ifNoneMatch = $request->headers->get('If-None-Match');
            if ($ifNoneMatch && $version === $ifNoneMatch) {
                return new Response('', Response::HTTP_NOT_MODIFIED);
            }
        } elseif ($this->concurrencyControl && self::isModifyingMethodRequest($request)) {
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
