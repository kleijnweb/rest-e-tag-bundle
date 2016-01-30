<?php
/*
 * This file is part of the KleijnWeb\RestETagBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KleijnWeb\RestETagBundle\Tests\Dev\EventListener;

use Doctrine\Common\Cache\ArrayCache;
use KleijnWeb\RestETagBundle\EventListener\ResponseListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class ResponseListenerTest extends \PHPUnit_Framework_TestCase
{
    const URI = '/foo/bar/bah';

    /**
     * @var ResponseListener
     */
    private $listener;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $eventMock;

    /**
     * @var ArrayCache
     */
    private $cache;

    /**
     * @var Response
     */
    private $response;

    /**
     * Create mocks
     */
    protected function setUp()
    {
        $this->eventMock = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Event\FilterResponseEvent')
            ->disableOriginalConstructor()
            ->getMock();
        $this->eventMock
            ->expects($this->once())
            ->method('isMasterRequest')
            ->willReturn(true);

        $this->eventMock
            ->expects($this->once())
            ->method('getResponse')
            ->willReturn($this->response = new Response());

        $this->cache = new ArrayCache();
        $this->listener = new ResponseListener($this->cache);
    }

    /**
     * @test
     */
    public function willIgnoreGetRequest()
    {
        $this->invokeListener('GET');
        $this->assertFalse($this->cache->fetch(self::URI));
    }

    /**
     * @test
     */
    public function willIgnoreHeadRequest()
    {
        $this->invokeListener('HEAD');
        $this->assertFalse($this->cache->fetch(self::URI));
    }

    /**
     * @test
     */
    public function willSetETagOnModifiedRequest()
    {
        $this->invokeListener('PATCH');
        $this->assertRegExp('/\d{10}\.\d+/', (string)$this->response->getEtag());
    }

    /**
     * @test
     */
    public function willSaveVersionOnModifiedRequest()
    {
        $this->invokeListener('PUT');
        $this->assertRegExp('/\d{10}\.\d+/', (string)$this->cache->fetch(self::URI));
    }

    /**
     * @test
     */
    public function willInvalidateAllParentPaths()
    {
        $this->invokeListener('PUT');
        $this->assertTrue($this->cache->contains('/foo'));
        $this->assertTrue($this->cache->contains('/foo/bar'));
        $this->assertTrue($this->cache->contains('/foo/bar/bah'));
    }

    /**
     * @param string $method
     */
    private function invokeListener($method)
    {
        $request = Request::create(self::URI, $method);

        $this->eventMock
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request);

        $this->listener->onKernelResponse($this->eventMock);
    }
}
