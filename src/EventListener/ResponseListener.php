<?php
/*
 * This file is part of the KleijnWeb\RestETagBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KleijnWeb\RestETagBundle\EventListener;

use Doctrine\Common\Cache\Cache;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class ResponseListener
{
    /**
     * @var Cache
     */
    private $cache;

    /**
     * @param Cache $cache
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
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
        $version = $this->cache->fetch($request->getPathInfo());

        $response = $event->getResponse();
        if (RequestListener::isModifyingRequest($request)) {
            $version = microtime(true);
            $path = $request->getPathInfo();
            $partialPath = '';
            foreach (explode('/', ltrim($path, '/')) as $segment) {
                $partialPath .= "/$segment";
                $this->cache->save($partialPath, $version);
            }
        }
        if ($version) {
            $response->headers->set('ETag', $version);
        }
    }
}
