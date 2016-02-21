<?php
/*
 * This file is part of the KleijnWeb\RestETagBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KleijnWeb\RestETagBundle\Tests\EventListener;

use Doctrine\Common\Cache\ArrayCache;
use KleijnWeb\RestETagBundle\Cache\CacheAdapter;
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
     * @var CacheAdapter
     */
    private $cacheAdapter;

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
            ->expects($this->any())
            ->method('isMasterRequest')
            ->willReturn(true);

        $this->eventMock
            ->expects($this->any())
            ->method('getResponse')
            ->willReturn($this->response = new Response());

        $this->cacheAdapter = new CacheAdapter(new ArrayCache());
        $this->listener = new ResponseListener($this->cacheAdapter);
    }

    /**
     * @test
     */
    public function getRequestDoesNotModifyVersion()
    {
        $this->invokeListener('GET');
        $originalVersion = $this->cacheAdapter->fetch(self::createRequest());

        for ($i = 0; $i < 10; ++$i) {
            $this->invokeListener('GET');
            $this->assertSame($originalVersion, $this->cacheAdapter->fetch(self::createRequest()));
        }
    }

    /**
     * @test
     */
    public function willIgnoreHeadRequest()
    {
        $this->invokeListener('HEAD');
        $this->assertEmpty($this->cacheAdapter->fetch(self::createRequest()));
    }

    /**
     * @test
     */
    public function willIgnoreOptionsRequest()
    {
        $this->invokeListener('HEAD');
        $this->assertEmpty($this->cacheAdapter->fetch(self::createRequest()));
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
        $this->assertRegExp('/\d{10}\.\d+/', $this->cacheAdapter->fetch(self::createRequest()));
    }

    /**
     * @test
     */
    public function willInvalidateAllParentPaths()
    {
        $this->invokeListener('PUT');
        $this->assertTrue($this->cacheAdapter->containsKey('/foo'));
        $this->assertTrue($this->cacheAdapter->containsKey('/foo/bar'));
        $this->assertTrue($this->cacheAdapter->containsKey('/foo/bar/bah'));
    }

    /**
     * @param string $method
     *
     * @return Request
     */
    private function invokeListener($method)
    {
        $request = Request::create(self::URI, $method);

        $this->eventMock
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($request);

        $this->listener->onKernelResponse($this->eventMock);

        return $request;
    }


    /**
     * @param string $method
     * @param array  $server
     *
     * @return Request
     */
    private static function createRequest($method = "GET", array $server = [])
    {
        return Request::create(self::URI, $method, [], [], [], $server);
    }
}
