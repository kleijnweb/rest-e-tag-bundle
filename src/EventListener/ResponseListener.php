<?php
/*
 * This file is part of the KleijnWeb\RestETagBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KleijnWeb\RestETagBundle\EventListener;

use KleijnWeb\RestETagBundle\Cache\CacheAdapter;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class ResponseListener
{
    /**
     * @var CacheAdapter
     */
    private $cacheAdapter;

    /**
     * @param CacheAdapter $cache
     */
    public function __construct(CacheAdapter $cache)
    {
        $this->cacheAdapter = $cache;
    }

    /**
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        $request = $event->getRequest();

        $response = $event->getResponse();
        if (substr((string)$response->getStatusCode(), 0, 1) !== '2') {
            // TODO UT this
            return;
        }

        if (RequestListener::isModifyingMethodRequest($request)) {
            $version = $this->cacheAdapter->update($request, (string)microtime(true));
        } elseif (!RequestListener::isIgnoreMethodRequest($request)) {
            if (!$version = $this->cacheAdapter->fetch($request)) {
                $version = $this->cacheAdapter->register($request, (string)microtime(true));
            }
        }
        if (isset($version)) {
            $response->headers->set('ETag', $version);
        }
    }
}
