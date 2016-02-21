<?php
/*
 * This file is part of the KleijnWeb\RestETagBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KleijnWeb\RestETagBundle\Tests\EventListener;

use Doctrine\Common\Cache\ArrayCache;
use KleijnWeb\RestETagBundle\Version\VersionStore;
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
     * @var VersionStore
     */
    private $store;

    /**
     * @var Response
     */
    private $response;

    /**
     * Create mocks
     */
    protected function setUp()
    {
        $this->eventMock = $this->createEventMock();
        $this->eventMock
            ->expects($this->any())
            ->method('getResponse')
            ->willReturn($this->response = new Response());

        $this->store = new VersionStore(new ArrayCache());
        $this->listener = new ResponseListener($this->store);
    }

    /**
     * @test
     */
    public function willIgnoreSubRequests()
    {
        $eventMock = $this->createEventMock(false);
        $eventMock->expects($this->never())->method('getRequest');
        $listener = new ResponseListener(new VersionStore(new ArrayCache()));
        $listener->onKernelResponse($eventMock);
    }

    /**
     * @test
     */
    public function getRequestDoesNotModifyVersion()
    {
        $this->invokeListener('GET');
        $originalVersion = $this->store->fetch(self::createRequest());

        for ($i = 0; $i < 10; ++$i) {
            $this->invokeListener('GET');
            $this->assertSame($originalVersion, $this->store->fetch(self::createRequest()));
        }
    }

    /**
     * @test
     */
    public function willIgnoreHeadRequest()
    {
        $this->invokeListener('HEAD');
        $this->assertEmpty($this->store->fetch(self::createRequest()));
    }

    /**
     * @test
     */
    public function willIgnoreOptionsRequest()
    {
        $this->invokeListener('HEAD');
        $this->assertEmpty($this->store->fetch(self::createRequest()));
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
        $this->assertRegExp('/\d{10}\.\d+/', $this->store->fetch(self::createRequest()));
    }

    /**
     * @test
     */
    public function willInvalidateAllParentPaths()
    {
        $this->invokeListener('PUT');
        $this->assertTrue($this->store->containsKey('/foo'));
        $this->assertTrue($this->store->containsKey('/foo/bar'));
        $this->assertTrue($this->store->containsKey('/foo/bar/bah'));
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
     * @param bool $masterRequest
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function createEventMock($masterRequest = true)
    {
        $mockEvent = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Event\FilterResponseEvent')
            ->disableOriginalConstructor()
            ->getMock();
        $mockEvent
            ->expects($this->any())
            ->method('isMasterRequest')
            ->willReturn($masterRequest);

        return $mockEvent;
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
