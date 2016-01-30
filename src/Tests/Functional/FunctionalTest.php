<?php
/*
 * This file is part of the KleijnWeb\SwaggerBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KleijnWeb\RestETagBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class FunctionalTest extends WebTestCase
{
    /**
     * @test
     */
    public function willAddETagForNewResources()
    {
        $client = self::createClient();
        $client->request('POST', '/foo/bar');
        $response = $client->getResponse();
        $this->assertRegExp('/\d{10}\.\d+/', (string)$response->getEtag());
    }

    /**
     * @test
     */
    public function willReturnNotModifiedResponseWhenUsingETag()
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->request('POST', '/foo/bar');
        $response = $client->getResponse();
        $client->request('GET', '/foo/bar', [], [], ['HTTP_IF_NONE_MATCH' => $response->getEtag()]);
        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function willAddETagForKnownResources()
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->request('POST', '/foo/bar');
        $response = $client->getResponse();
        $eTag = (string)$response->getEtag();
        $client->request('GET', '/foo/bar');
        $response = $client->getResponse();
        $this->assertSame($eTag, (string)$response->getEtag());
    }

    /**
     * @test
     */
    public function willDoResourceLocking()
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->request('POST', '/foo/bar');
        $staleETag = (string)$client->getResponse()->getEtag();
        $client->request('POST', '/foo/bar');
        $client->request('POST', '/foo/bar', [], [], ['HTTP_IF_MATCH' => $staleETag]);
        $this->assertSame(Response::HTTP_PRECONDITION_FAILED, $client->getResponse()->getStatusCode());
    }
}
