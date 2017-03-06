<?php
namespace NamelessCoder\MultilevelCache;

/**
 * Class CacheConfiguration
 */
class CacheConfiguration
{
    /**
     * @param array ...$caches
     */
    public static function convert(...$caches)
    {
        $target = array_shift($caches);
        $currentConfiguration = static::getCacheConfiguration($target);

        $backends = [];
        foreach ($caches as $cacheDefinition) {
            if (is_string($cacheDefinition)) {
                $cacheDefinitionName = $cacheDefinition;
                $cacheDefinition = static::getCacheConfiguration($cacheDefinitionName);
                if ($cacheDefinitionName === 'cache_runtime' || ($cacheDefinition['multilevel']['prefix'] ?? false) === true) {
                    $cacheDefinition['multilevel']['prefix'] = $target;
                }
            }
            $backends[] = $cacheDefinition;
        }

        static::setCacheConfiguration(
            $target,
            [
                'frontend' => $currentConfiguration['frontend'],
                'backend' => MultilevelCacheBackend::class,
                'options' => [
                    'original' => $target,
                    'backends' => $backends
                ]
            ]
        );
    }

    /**
     * @param string $name
     * @return array
     */
    protected static function getCacheConfiguration($name)
    {
        return $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$name];
    }

    /**
     * @param string $name
     * @param array $configuration
     * @return array
     */
    protected static function setCacheConfiguration($name, array $configuration)
    {
        return $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$name] = $configuration;
    }

}
