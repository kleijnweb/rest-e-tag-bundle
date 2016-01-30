# KleijnWeb\RestETagBundle [![Build Status](https://travis-ci.org/kleijnweb/rest-e-tag-bundle.svg?branch=master)](https://travis-ci.org/kleijnweb/rest-e-tag-bundle)

Small bundle that adds caching and Concurrency Control for REST APIs using E-Tag Headers.

Go to the [release page](https://github.com/kleijnweb/rest-e-tag-bundle/releases) to find details about the latest release.

## Functional Details

RestETagBundle uses REST semantics to form a cache invalidation and optimistic concurrency strategy.
 
* Versions the resources your URI paths represents and keeps this list in a server side cache.
* Increments the version of a path when one of the following methods is used: POST, PUT, PATCH, DELETE
* Increments the version of all parent paths when that of a child in incremented 
* Ensures the tag passed using If-Match matches the ETag in the cache, returns HTTP 412 in case of discrepancy.
* Returns HTTP 428 responses when concurrency control is enabled and the appropriate header is missing.

The bundle uses microtime based version IDs to prevent loss of the server side cache causing collisions and sub-second resource locking.

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
   
## License

KleijnWeb\RestETagBundle is made available under the terms of the [LGPL, version 3.0](https://spdx.org/licenses/LGPL-3.0.html#licenseText).
