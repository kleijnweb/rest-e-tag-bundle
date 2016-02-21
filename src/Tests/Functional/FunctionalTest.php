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
    public function willInvalidateWhenPostingToParent()
    {
        $this->assertWillInvalidateWhenPostingToParent('/foo/bar', '/foo/bar/doh');
    }

    /**
     * @test
     */
    public function willTreatQueryAsASegment()
    {
        $this->assertWillInvalidateWhenPostingToParent('/foo/bar', '/foo/bar?doh=1');
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
        $client->request('POST', '/foo/bar', [], [], ['HTTP_IF_MATCH' => $staleETag]);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $client->request('POST', '/foo/bar', [], [], ['HTTP_IF_MATCH' => $staleETag]);
        $this->assertSame(Response::HTTP_PRECONDITION_FAILED, $client->getResponse()->getStatusCode());
    }

    /**
     * @param string $parentUrl
     * @param string $childUrl
     */
    public function assertWillInvalidateWhenPostingToParent($parentUrl, $childUrl)
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->request('GET', $childUrl);
        $response = $client->getResponse();
        $originalEtag = $response->getEtag();
        $this->assertNotEmpty($originalEtag);

        // Sanity check
        $client->request('GET', $childUrl, [], [], ['HTTP_IF_NONE_MATCH' => $originalEtag]);
        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());

        // Validate that when we post to what should be the parent, the resource is marked as modified
        // Strict concurrency control is turned on, so we always need to pass the current E-Tag.
        // Since we just registered a child path, the correct key is the same
        $client->request('POST', $parentUrl, [], [], ['HTTP_IF_MATCH' => $originalEtag]);

        // Sanity check
        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Now validate the child is re-evaluated
        $client->request('GET', $childUrl, [], [], ['HTTP_IF_NONE_MATCH' => $originalEtag]);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
