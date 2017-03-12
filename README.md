TYPO3 CMS Multilevel Caching
============================

A tiny TYPO3 extension which does one thing:

> Adds a new cache backend which delegates to multiple prioritized cache backends

What does this mean?

It basically means that you can combine and prioritize multiple cache backends for TYPO3 caches. The main use case is to
combine a transient memory backend and persisted backend; which causes TYPO3 to only consult "slow" caches when this is
absolutely necessary, instead returning the variable from active memory every time it normally would have been retrieved
from the persistent/slow storage.

You can also combine caches in other ways:

* Local SQL after lookup in Redis
* Memcached after lookup in local SQL and Redis
* Transient memory after lookup in files and local SQL
* And so on, with any available backends


Target audience
---------------

The main target audience for this extension is **sites which use any type of remote stored caches and wish to reduce
latency for repeated cache fetch requests**. If your site uses a local database storage you most likely won't see any
benefit from installing and using this extension.

The philosophy is simple: any request to caches on remote DB or other server that can be avoided, should be avoided.
Multiple requests to fetch the same cached resource should always return the resource from memory to reduce latency.


Installing
----------

Only available through Packagist:

```
composer require namelesscoder/typo3-cms-multilevel-cache
```

Depending on your setup you may need to activate the extension via the TYPO3 Extension Manager afterwards.


Configuring
-----------

There is only one way to configure multilevel caching backends: by changing the cache configurations that exist in
global TYPO3 configuration. This means you must put your configuration in one of two possible places:

* `ext_localconf.php` of an extension (if you don't already use it, the "site extension" pattern is recommended!)
* `AdditionalConfiguration.php` of your TYPO3 site

In both places you can convert existing caches to multilevel backends in the following way:

```php

// Simplest, most frequent use case: put runtime cache on top of existing cache
\NamelessCoder\MultilevelCache\CacheConfiguration::convert(
    'extbase_object',
    'cache_runtime', // First priority. A string means this is a reference to another cache that's already defined.
    'extbase_object' // Second priority. A fast way to operate this is to reference the original configuration.
);

// Bit more complex, manually define the second, third, fourth and so on backends that will be used:
\NamelessCoder\MultilevelCache\CacheConfiguration::convert(
    'extbase_object',
    'cache_runtime', // First priority. A string means this is a reference to another cache that's already defined.
    [
        'backend' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
        'options' => [
            'defaultLifetime' => 0,
        ]
    ]
);
```

Note that the original cache (in this case `extbase_object`) MUST be defined. It is NOT POSSIBLE to configure a cache
frontend through the above API and so this must be configured manually.

The last example shows the different configuration options that are *added to* the "options" array in order to configure
the behavior of the combined backend:

```php
\NamelessCoder\MultilevelCache\CacheConfiguration::convert(
    'extbase_object',
    'cache_runtime', // First priority. A string means this is a reference to another cache that's already defined.
    [
        'backend' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
        'options' => [
            'defaultLifetime' => 86400
        ],
        'multilevel' => [
            'flush => true, // Flush this backend when the cache is flushed. Default `true`.
            'cascade' => true, // Do `set()` and `remove()` also on this backend. Default `true`.
            'prefix' => 'combo-extbase-object', // All items' IDs are prefixed with this value. Default is empty.
        ]
    ],
    [
        'backend' => My\Extension\OffSiteCacheBackend::class,
        'options' => [
            'defaultLifetime' => 604800
        ],
        'multilevel' => [
            'flush => false
        ]
    ]
);
```

Finally, the `cache_runtime` cache is automatically fitted with `flush = true`, `cascade = true` and an automated prefix
containing the original cache's identifier, as to avoid any potential collisions. This is done to make it easier to
reference this particular cache in the combined configuration since this is the most frequent use case and the desired
behavior is exactly to have the runtime cache store anything returned from lower-priority cache backends.

**Note that this makes the runtime cache different from other caches:**

* This cache will be automatically flushed when it is combined with other caches.
* Entries in the cache have an identifier which is different from the lower-level caches (prefixed)

The automatic prefix defaults to the name of the cache you are replacing. This means that if you store something with
a cache ID of `foobar-value` in cache `mycache_special` which you converted to multilayer caching, the cache ID in the
topmost runtime cache layer becomes `mycache_special_foobar-value`. **This works transparently and you never have to
pass the prefix for a cache ID - it only applies to you if for some reason you want to access multilevel cached values
directly from the runtime cache separately from the MultilevelCacheBackend.**


A few pointers
--------------

Before you use this extension you should be aware of the following:

1. When you define combined cache backends, those that do not support tags will simply ignore tags.
2. This also means that when flushing by tags, **non-tag-capable backends in the combined set get flushed completely**.
3. When you perform a flush operation, all backends are flushed.
4. Be careful when you use this with remote caches such as Redis. If your slaves share caches, bulk flushing could
   result in load spikes - this is normal with remote caches, but is more important to consider when running multiple
   caches that all get flushed at the same time.
5. Cache identifiers will be the same in all caches that go into a combined configuration. To avoid this, use the
   `prefix` option as shown above.


Credits
-------

Part of this work was sponsored by LINKS DER ISAR GmbH - http://www.linksderisar.com/
