<?php
namespace NamelessCoder\MultilevelCache;

/**
 * Class CacheConfiguration
 */
class CacheConfiguration
{
    /**
     * @param array ...$caches
     *
     */
    public static function convert(...$caches)
    {
        $target = array_shift($caches);
        $currentConfiguration = static::getCacheConfiguration($target);
        $newConfiguration = [
            'frontend' => $currentConfiguration['frontend'],
            'backend' => MultilevelCacheBackend::class,
            'options' => [
                'backends' => []
            ]
        ];
        foreach ($caches as $backendDefinition) {
            $configuration = [];
            if (is_array($backendDefinition)) {
                $configuration = $backendDefinition;
            } elseif (is_string($backendDefinition)) {
                $configuration = static::getCacheConfiguration($backendDefinition);
                if ($backendDefinition === 'cache_runtime') {
                    $configuration['multilevel']['cascade'] = true;
                    $configuration['multilevel']['prefix'] = $target;
                }
            }
            $newConfiguration['options']['backends'][] = $configuration;
        }

        static::setCacheConfiguration($target, $newConfiguration);
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
