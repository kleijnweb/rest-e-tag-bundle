<?php
/*
 * This file is part of the KleijnWeb\RestETagBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KleijnWeb\RestETagBundle\EventListener;

use KleijnWeb\RestETagBundle\Version\VersionStore;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class ResponseListener
{
    /**
     * @var VersionStore
     */
    private $store;

    /**
     * @param VersionStore $cache
     */
    public function __construct(VersionStore $cache)
    {
        $this->store = $cache;
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
            $version = $this->store->update($request, (string)microtime(true));
        } elseif (!RequestListener::isIgnoreMethodRequest($request)) {
            if (!$version = $this->store->fetch($request)) {
                $version = $this->store->register($request, (string)microtime(true));
            }
        }
        if (isset($version)) {
            $response->headers->set('ETag', $version);
        }
    }
}
