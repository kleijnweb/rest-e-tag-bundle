<?php
/*
 * This file is part of the KleijnWeb\RestETagBundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KleijnWeb\RestETagBundle\Cache;

use Doctrine\Common\Cache\Cache;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author John Kleijn <john@kleijnweb.nl>
 */
class CacheAdapter
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
        if (!$record = $this->cache->fetch($this->createKey($request))) {
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
            $record = $this->cache->fetch($path);
            if ($record) {
                $this->invalidateChildren($record[self::KEY_CHILDREN], $version);
            } else {
                $record = [self::KEY_CHILDREN => []];
            }
            $record[self::KEY_VERSION] = $version;
            if (isset($paths[$i + 1])) {
                $record[self::KEY_CHILDREN][] = $paths[$i + 1];
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
            $record = $this->cache->fetch($path);
            if (!$record) {
                $record = [self::KEY_VERSION => $version, self::KEY_CHILDREN => []];
            }
            if (isset($paths[$i + 1])) {
                $record[self::KEY_CHILDREN][] = $paths[$i + 1];
            }
            $this->cache->save($path, $record);
        }
        $record = [self::KEY_VERSION => $version, self::KEY_CHILDREN => []];
        $this->cache->save($this->createKeyFromSegments($segments), $record);

        return $version;
    }

    private function invalidateChildren(array $children, $version)
    {
        foreach ($children as $child) {
            if ($this->childInvalidationConstraint !== ''
                && preg_match("/$this->childInvalidationConstraint/", $child)
            ) {
                // Stop recursive invalidation if it matches
                return;
            }
            $record = $this->cache->fetch($child);
            $record[self::KEY_VERSION] = $version;
            $this->cache->save($child, $record);
            if ($record) {
                $this->invalidateChildren($record[self::KEY_CHILDREN], $version);
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
     * @param array $segments
     *
     * @return string
     */
    private function createKeyFromSegments(array $segments)
    {
        return '/' . implode('/', $segments);
    }
}
