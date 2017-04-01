<?php
/*
 * This file is part of the KleijnWeb\RestETagBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KleijnWeb\RestETagBundle\Tests\EventListener;

use Symfony\Component\Cache\Simple\ArrayCache;
use KleijnWeb\RestETagBundle\Version\VersionStore;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class VersionStoreTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var VersionStore
     */
    private $store;

    /**
     * Create mocks
     */
    protected function setUp()
    {
        $this->store = new VersionStore(new ArrayCache());
    }

    /**
     * @test
     */
    public function canSaveAndFetchUsingPath()
    {
        $uri = "/a/b/cee/dee/eff";
        $version = 'version';
        $this->store->update(Request::create($uri), $version);
        $this->assertSame($version, $this->store->fetch(Request::create($uri)));
    }

    /**
     * @test
     */
    public function canSaveAndFetchUsingPathAndQuery()
    {
        $uri = "/a/b/cee/?dee=eff";
        $version = 'version';
        $this->store->update(Request::create($uri), $version);
        $this->assertSame($version, $this->store->fetch(Request::create($uri)));
    }

    /**
     * @test
     */
    public function willInsertSlashBeforeQuery()
    {
        $uri = "/a/b/cee?dee=eff";
        $version = 'version';
        $this->store->update(Request::create($uri), $version);
        $this->assertSame($version, $this->store->fetch(Request::create("/a/b/cee/?dee=eff")));
    }

    /**
     * @test
     */
    public function willUrlEncodeQuery()
    {
        $uri = "/a/b/cee/?dee=eff/";
        $version = 'version';
        $this->store->update(Request::create($uri), $version);
        $this->assertSame($version, $this->store->fetch(Request::create("/a/b/cee/?dee=eff%2F")));
    }

    /**
     * @test
     */
    public function willFilterEmptySegments()
    {
        $version1 = 'version1';
        $this->store->update(Request::create("/a/b///cee/"), $version1);
        $this->assertSame($version1, $this->store->fetch(Request::create('/a/b/cee')));
        $version2 = 'version2';
        $this->store->update(Request::create("/a/b/cee//?dee=eff"), $version2);
        $this->assertSame($version2, $this->store->fetch(Request::create('/a/b/cee/?dee=eff')));
    }

    /**
     * @test
     */
    public function willFilterNonPrintable()
    {
        $uri = "/a/b/" . chr(6) . "cee/?" . chr(6) . "dee=eff";
        $version = 'version';
        $this->store->update(Request::create($uri), $version);
        // The underscores are introduced by parse_url()
        $this->assertSame($version, $this->store->fetch(Request::create("/a/b/_cee/?_dee=eff")));
    }

    /**
     * @test
     */
    public function willFilterNonAscii()
    {
        $uri = "/aنقاط/你好b，世界";
        $version = 'version';
        $this->store->update(Request::create($uri), $version);
        // The underscores are introduced by preg_replace()
        $this->assertSame($version, $this->store->fetch(Request::create("/a________/______b_________")));
    }

    /**
     * @test
     */
    public function willSaveSegmentsIndividually()
    {
        $uri = "/a/b/cee/?dee=eff";
        $version = 'version';
        $this->store->update(Request::create($uri), $version);
        $this->assertSame($version, $this->store->fetch(Request::create("/a")));
        $this->assertSame($version, $this->store->fetch(Request::create("/a/b")));
        $this->assertSame($version, $this->store->fetch(Request::create("/a/b/cee")));
        $this->assertSame($version, $this->store->fetch(Request::create("/a/b/cee/?dee=eff")));

        $this->assertSame('', $this->store->fetch(Request::create("/something/else")));
    }

    /**
     * @test
     */
    public function canRegisterPathVersion()
    {
        $uri = "/a/b/cee/?dee=eff";
        $version = 'version';
        $this->store->register(Request::create($uri), $version);
        $this->assertSame($version, $this->store->fetch(Request::create($uri)));
    }

    /**
     * @test
     */
    public function canConfirmContainsVersionForRequest()
    {
        $uri = "/a/b/cee/?dee=eff";
        $this->store->register(Request::create($uri), microtime(true));
        $this->assertTrue($this->store->contains(Request::create($uri)));
    }

    /**
     * @test
     */
    public function updatingRootInvalidatesChildren()
    {
        $childUri = "/a/b/cee/?dee=eff";
        $childVersion = 'childVersion';
        $this->store->update(Request::create($childUri), $childVersion);

        $parentUri = "/a";
        $parentVersion = 'parentVersion';
        $this->store->update(Request::create($parentUri), $parentVersion);

        $this->assertSame($parentVersion, $this->store->fetch(Request::create("/a")));
        $this->assertSame($parentVersion, $this->store->fetch(Request::create("/a/b")));
        $this->assertSame($parentVersion, $this->store->fetch(Request::create("/a/b/cee")));
        $this->assertSame($parentVersion, $this->store->fetch(Request::create("/a/b/cee/?dee=eff")));
    }

    /**
     * @test
     */
    public function updatingParentInvalidatesChildrenAndParents()
    {
        $childUri = "/a/b/cee/dee/?eff=gee";
        $originalVersion = 'originalVersion';
        $this->store->update(Request::create($childUri), $originalVersion);

        $parentUri = "/a/b";
        $newVersion = 'newVersion';
        $this->store->update(Request::create($parentUri), $newVersion);

        $this->assertSame($newVersion, $this->store->fetch(Request::create("/a")));
        $this->assertSame($newVersion, $this->store->fetch(Request::create("/a/b")));
        $this->assertSame($newVersion, $this->store->fetch(Request::create("/a/b/cee")));
        $this->assertSame($newVersion, $this->store->fetch(Request::create("/a/b/cee/dee")));
        $this->assertSame($newVersion, $this->store->fetch(Request::create("/a/b/cee/dee/?eff=gee")));
    }

    /**
     * @test
     */
    public function registeredChildrenAreInvalidated()
    {
        $uri = "/a/b/cee/?dee=eff";
        $this->store->register(Request::create($uri), 'oldVersion');
        $this->assertTrue($this->store->contains(Request::create($uri)));

        $parentUri = "/a/b";
        $newVersion = 'newVersion';
        $this->store->update(Request::create($parentUri), $newVersion);

        $this->assertSame($newVersion, $this->store->fetch(Request::create("/a")));
        $this->assertSame($newVersion, $this->store->fetch(Request::create("/a/b")));
        $this->assertSame($newVersion, $this->store->fetch(Request::create("/a/b/cee")));
        $this->assertSame($newVersion, $this->store->fetch(Request::create($uri)));
    }

    /**
     * @test
     */
    public function registerWillNotUpdateVersionOfExistingParent()
    {
        $parentUri = "/a/b";
        $parentVersion = 'parentVersion';
        $this->store->update(Request::create($parentUri), $parentVersion);

        $childUri = "/a/b/cee/dee/?eff=gee";
        $childVersion = 'childVersion';
        $this->store->register(Request::create($childUri), $childVersion);

        $this->assertSame($parentVersion, $this->store->fetch(Request::create("/a")));
        $this->assertSame($parentVersion, $this->store->fetch(Request::create("/a/b")));
        $this->assertSame($childVersion, $this->store->fetch(Request::create("/a/b/cee")));
        $this->assertSame($childVersion, $this->store->fetch(Request::create("/a/b/cee/dee")));
        $this->assertSame($childVersion, $this->store->fetch(Request::create("/a/b/cee/dee/?eff=gee")));
    }

    /**
     * @test
     */
    public function updateWillUpdateVersionOfExistingParent()
    {
        $parentUri = "/a/b";
        $parentVersion = 'parentVersion';
        $this->store->update(Request::create($parentUri), $parentVersion);

        $childUri = "/a/b/cee/dee/?eff=gee";
        $childVersion = 'childVersion';
        $this->store->update(Request::create($childUri), $childVersion);

        $this->assertSame($childVersion, $this->store->fetch(Request::create("/a")));
        $this->assertSame($childVersion, $this->store->fetch(Request::create("/a/b")));
        $this->assertSame($childVersion, $this->store->fetch(Request::create("/a/b/cee")));
        $this->assertSame($childVersion, $this->store->fetch(Request::create("/a/b/cee/dee")));
        $this->assertSame($childVersion, $this->store->fetch(Request::create("/a/b/cee/dee/?eff=gee")));
    }

    /**
     * @test
     */
    public function savingParentInvalidatesParentsAndOnlyChildrenNotMatchingConstraint()
    {
        $childUri = "/a/b/cee/dee/?eff=gee";
        $originalVersion = 'originalVersion';
        $this->store->setChildInvalidationConstraint('\/dee$');
        $this->store->update(Request::create($childUri), $originalVersion);

        $parentUri = "/a/b";
        $newVersion = 'newVersion';
        $this->store->update(Request::create($parentUri), $newVersion);

        $this->assertSame($newVersion, $this->store->fetch(Request::create("/a")));
        $this->assertSame($newVersion, $this->store->fetch(Request::create("/a/b")));
        $this->assertSame($newVersion, $this->store->fetch(Request::create("/a/b/cee")));
        $this->assertSame($originalVersion, $this->store->fetch(Request::create("/a/b/cee/dee")));
        $this->assertSame($originalVersion, $this->store->fetch(Request::create("/a/b/cee/dee/?eff=gee")));
    }
}
