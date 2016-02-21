# KleijnWeb\RestETagBundle
[![Build Status](https://travis-ci.org/kleijnweb/rest-e-tag-bundle.svg?branch=master)](https://travis-ci.org/kleijnweb/rest-e-tag-bundle)
[![Coverage Status](https://coveralls.io/repos/github/kleijnweb/rest-e-tag-bundle/badge.svg?branch=master)](https://coveralls.io/github/kleijnweb/rest-e-tag-bundle?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/kleijnweb/rest-e-tag-bundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/kleijnweb/rest-e-tag-bundle/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/kleijnweb/rest-e-tag-bundle/v/stable)](https://packagist.org/packages/kleijnweb/rest-e-tag-bundle)

Small bundle that adds caching and Concurrency Control for REST APIs using E-Tag Headers.

Go to the [release page](https://github.com/kleijnweb/rest-e-tag-bundle/releases) to find details about the latest release.

For an example see [swagger-bundle-example](https://github.com/kleijnweb/swagger-bundle-example).

## Functional Details

RestETagBundle uses REST semantics to form a cache invalidation and optimistic concurrency strategy.
 
* Versions the resources your URI paths represents and keeps this list in a server side cache.
* Increments the version of a path when one of the following methods is used: POST, PUT, PATCH, DELETE
* Increments the version of all parent and selected "lower" paths when that of a child in incremented 
* Ensures the tag passed using If-Match matches the ETag in the cache, returns HTTP 412 in case of discrepancy.
* Returns HTTP 428 responses when concurrency control is enabled and the appropriate header is missing.

The bundle uses microtime based version IDs to prevent loss of the server side cache causing collisions and sub-second resource locking. Removes all non-printable and non-ascii chars from URLs before using them as cache keys.
 
The versioning scheme is pretty straightforward, examples:

 * Modifying `/animals/rabbits/1`: invalidates `/animals`, `/animals/rabbits`, `/animals/rabbits/1`, and (if it exists) `/animals/rabbits/1/relations/owners`
 * GET on `/animals/rabbits/2`: this is not effected by the previous example. In addition, this will create a version if none exists yet (without invalidating anything)
 * Modifying  `/animals/rabbits`: both `/animals` and `/animals/rabbits` get a new version. 
   So will any existing versions matching the child invalidation constraint (see configuration), eg `/animals/rabbits/findByName`
 
The query part of the URL is treated as the last path segment:

 * Modifying `/animals?type=rabbits`: will be interpreted as modification of `/animals/?type=rabbits`. So `/animals` will be invalidated.
 * GET on `/animals?type=rabbits`: will be interpreted as GET `/animals/?type=rabbits`.
 * Modifying `/animals/rabbits?id=1`: will be interpreted as a modification of `/animals/rabbits/?id=1`. So the old versions of both `/animals` and `/animals/rabbits` are invalidated too.
 * GET on `/animals?type=dogs`: will be interpreted as GET `/animals/?type=dogs`. So a modification of `/animals?type=rabbits` will not affect it (but modification of `/animals` will invalidate it). 
 
The default child invalidation constraint is a negated regular expression: `\/[0-9]+$`. This means a POST to `/animals/rabbits` will by default not invalidate `/animals/rabbits/1` or any paths below it, but will invalidate `/animals/rabbits/findByName`.

NOTE: The store and retrieve calls are not yet fully optimized and get pretty chatty when using network based caches. You can probably expect best performance from APCu. It won't use that much memory.

## Install And Configure

Install using composer (`composer require kleijnweb/rest-e-tag-bundle`). You want to check out the [release page](https://github.com/kleijnweb/rest-e-tag-bundle/releases) to ensure you are getting what you want and optionally verify your download.

Concurrency control is enabled by default. To disable:

```yml
rest_e_tags:
  concurrency_control: false
```
The bundle will work with any Doctrine cache. Use the 'cache' config option to reference the service to be used:

```yml
rest_e_tags:
  cache: my.doctrine.cache
```
   
You can tweak the default child invalidation constraint (negated, see default above):

```yml
rest_e_tags:
  # Do not invalidate paths that look like they end in UUIDs (nor any paths below them)
  child_invalidation_constraint: '\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'
``` 

```yml
rest_e_tags:
  # Always invalidate, skip regex match
  child_invalidation_constraint: ''
```
## License

KleijnWeb\RestETagBundle is made available under the terms of the [LGPL, version 3.0](https://spdx.org/licenses/LGPL-3.0.html#licenseText).
