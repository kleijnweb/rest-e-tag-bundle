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
use KleijnWeb\RestETagBundle\EventListener\RequestListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class RequestListenerTest extends \PHPUnit_Framework_TestCase
{
    const URI = '/foo/bar';

    /**
     * @var RequestListener
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
     * Create mocks
     */
    protected function setUp()
    {
        $this->eventMock = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent')
            ->disableOriginalConstructor()
            ->getMock();

        $this->eventMock
            ->expects($this->once())
            ->method('isMasterRequest')
            ->willReturn(true);

        $this->cacheAdapter = new CacheAdapter(new ArrayCache());
        $this->listener = new RequestListener($this->cacheAdapter, true);
    }

    /**
     * @test
     */
    public function limitAllowedMethods()
    {
        $this->eventMock
            ->expects($this->once())
            ->method('setResponse')
            ->with($this->callback(function (Response $response) {
                return $response->getStatusCode() === Response::HTTP_METHOD_NOT_ALLOWED;
            }));

        $this->invokeListener('FAUX');
        $this->assertEmpty($this->cacheAdapter->fetch(self::createRequest()));
    }

    /**
     * @test
     */
    public function willCreateNotModifiedResponseCacheHasMatch()
    {
        $version = (string)microtime(true);
        $this->cacheAdapter->update(self::createRequest(), $version);

        $this->eventMock
            ->expects($this->once())
            ->method('setResponse')
            ->with($this->callback(function (Response $response) {
                return $response->getStatusCode() === Response::HTTP_NOT_MODIFIED;
            }));


        $this->invokeListener('GET', ['HTTP_IF_NONE_MATCH' => $version]);
    }

    /**
     * @test
     */
    public function willNotCreateResponseWhenCacheIsEmpty()
    {
        $version = (string)microtime(true);

        $this->eventMock
            ->expects($this->never())
            ->method('setResponse');

        $this->invokeListener('GET', ['HTTP_IF_NONE_MATCH' => $version]);
    }

    /**
     * @test
     */
    public function willNotCreateResponseWhenVersionDoesNotMatch()
    {
        $version1 = microtime(true);
        $version2 = $version1 + 1;

        $this->cacheAdapter->update(self::createRequest(), (string)$version1);
        $this->eventMock
            ->expects($this->never())
            ->method('setResponse');

        $this->invokeListener('GET', ['HTTP_IF_NONE_MATCH' => (string)$version2]);
    }

    /**
     * @test
     */
    public function willCreatePreconditionRequiredWhenHeaderIsMissing()
    {
        $version1 = (string)microtime(true);

        $this->cacheAdapter->update(self::createRequest(), $version1);

        $this->eventMock
            ->expects($this->once())
            ->method('setResponse')
            ->with($this->callback(function (Response $response) {
                return $response->getStatusCode() === Response::HTTP_PRECONDITION_REQUIRED;
            }));

        $this->invokeListener('POST');
    }

    /**
     * @test
     */
    public function willCreatePreconditionFailedWhenVersionMismatch()
    {
        $version1 = microtime(true);
        $version2 = $version1 + 1;

        $this->cacheAdapter->update(self::createRequest(), (string)$version1);

        $this->eventMock
            ->expects($this->once())
            ->method('setResponse')
            ->with($this->callback(function (Response $response) {
                return $response->getStatusCode() === Response::HTTP_PRECONDITION_FAILED;
            }));

        $this->invokeListener('POST', ['HTTP_IF_MATCH' => (string)$version2]);
    }

    /**
     * @param string $method
     * @param array  $server
     */
    private function invokeListener($method, array $server = [])
    {
        $request = self::createRequest($method, $server);

        $this->eventMock
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request);

        $this->listener->onKernelRequest($this->eventMock);
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
