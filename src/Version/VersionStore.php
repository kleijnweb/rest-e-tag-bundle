<?php
/*
 * This file is part of the KleijnWeb\RestETagBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KleijnWeb\RestETagBundle\Version;

use Doctrine\Common\Cache\Cache;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class VersionStore
{
    const KEY_VERSION = 1;
    const KEY_CHILDREN = 2;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var string
     */
    private $childInvalidationConstraint;

    /**
     * @param Cache  $cache
     * @param string $childInvalidationConstraint
     */
    public function __construct(Cache $cache, $childInvalidationConstraint = '')
    {
        $this->cache = $cache;
        $this->childInvalidationConstraint = $childInvalidationConstraint;
    }

    /**
     * @param string $childInvalidationConstraint
     *
     * @return $this
     */
    public function setChildInvalidationConstraint($childInvalidationConstraint)
    {
        $this->childInvalidationConstraint = $childInvalidationConstraint;

        return $this;
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    public function fetch(Request $request)
    {
        if (!$record = $this->fetchRecord($this->createKey($request))) {
            return '';
        }

        return $record[self::KEY_VERSION];
    }

    /**
     * @param Request $request
     *
     * @return bool
     */
    public function contains(Request $request)
    {
        return $this->containsKey($this->createKey($request));
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function containsKey($key)
    {
        return $this->cache->contains($key);
    }

    /**
     * @param Request $request
     * @param string  $version
     *
     * @return mixed
     */
    public function update(Request $request, $version)
    {
        $segments = $this->getSegments($request);
        $paths = [];
        $path = '';
        foreach ($segments as $segment) {
            $path .= "/$segment";
            $paths[] = $path;
        }

        foreach ($paths as $i => $path) {
            $record = $this->fetchRecord($path);
            if ($record) {
                $this->invalidateChildren($record, $version);
            } else {
                $record = $this->createRecord();
            }
            $this->updateVersion($record, $version);
            if (isset($paths[$i + 1])) {
                $this->addChild($record, $paths[$i + 1]);
            }
            $this->cache->save($path, $record);
        }

        return $version;
    }

    /**
     * @param Request $request
     * @param string  $version
     *
     * @return mixed
     */
    public function register(Request $request, $version)
    {
        $segments = $this->getSegments($request);
        $paths = [];
        $path = '';
        foreach ($segments as $segment) {
            $path .= "/$segment";
            $paths[] = $path;
        }

        foreach ($paths as $i => $path) {
            $record = $this->fetchRecord($path);
            if (!$record) {
                $record = $this->createRecord($version);
            }
            if (isset($paths[$i + 1])) {
                $this->addChild($record, $paths[$i + 1]);
            }
            $this->saveRecord($path, $record);
        }
        $record = $this->createRecord($version);
        $this->cache->save($this->createKeyFromSegments($segments), $record);

        return $version;
    }

    /**
     * @param array  $record
     * @param string $version
     */
    private function invalidateChildren(array $record, $version)
    {
        foreach ($record[self::KEY_CHILDREN] as $child) {
            if ($this->childInvalidationConstraint !== ''
                && preg_match("/$this->childInvalidationConstraint/", $child)
            ) {
                // Stop recursive invalidation if it matches
                return;
            }
            $record = $this->fetchRecord($child);
            $this->updateVersion($record, $version);
            $this->saveRecord($child, $record);
            if ($record) {
                $this->invalidateChildren($record, $version);
            }
        }
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    private function getSegments(Request $request)
    {
        $key = $request->getPathInfo();
        $segments = explode('/', ltrim($key, '/'));
        if ($query = $request->getQueryString()) {
            $segments[] = '?' . $query;
        }

        array_walk($segments, function (&$value) {
            $value = preg_replace('/[^[:print:]]/', '_', $value);
        });

        return array_filter($segments, function ($value) {
            return $value !== '';
        });
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    private function createKey(Request $request)
    {
        return $this->createKeyFromSegments($this->getSegments($request));
    }

    /**
     * @param string $key
     * @param array  $record
     */
    private function saveRecord($key, array $record)
    {
        $this->cache->save($key, $record);
    }

    /**
     * @param string $key
     *
     * @return array
     */
    private function fetchRecord($key)
    {
        return $this->cache->fetch($key);
    }

    /**
     * @param array  $children
     * @param string $version
     *
     * @return array
     */
    private function createRecord($version = '', $children = [])
    {
        return [self::KEY_VERSION => $version, self::KEY_CHILDREN => $children];
    }

    /**
     * @param array  $record
     * @param string $version
     */
    private function updateVersion(array &$record, $version)
    {
        $record[self::KEY_VERSION] = $version;
    }

    /**
     * @param array  $record
     * @param string $key
     */
    private function addChild(array &$record, $key)
    {
        if (!isset($record[self::KEY_CHILDREN])) {
            $record[self::KEY_CHILDREN] = [];
        }
        $record[self::KEY_CHILDREN][] = $key;
    }

    /**
     * @param array $segments
     *
     * @return string
     */
    private function createKeyFromSegments(array $segments)
    {
        return '/' . implode('/', $segments);
    }
}
