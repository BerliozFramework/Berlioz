Cache
=====

**Berlioz** integrate a cache service but subtract management to another lib. We recommend to use **phpFastCache**.
The cache service MUST implements `\Psr\SimpleCache\CacheInterface` interface.

## Example ###

```php
// Configuration
$config = new \Berlioz\Core\Config(__ROOT_DIR__, '/config/config.json');

// Cache manager
$cacheManager = new phpFastCache\Helper\Psr16Adapter('Files', ['path' => $config->getDirectory(\Berlioz\Core\ConfigInterface::DIR_VAR_CACHE)]);

// Application
$app = new \Berlioz\Core\App($config);
$app->getServices()->set('cache', $cacheManager);
```