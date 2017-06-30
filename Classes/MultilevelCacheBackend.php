<?php
namespace NamelessCoder\MultilevelCache;

use TYPO3\CMS\Core\Cache\Backend\AbstractBackend;
use TYPO3\CMS\Core\Cache\Backend\BackendInterface;
use TYPO3\CMS\Core\Cache\Backend\TaggableBackendInterface;
use TYPO3\CMS\Core\Cache\Backend\TransientBackendInterface;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Class MultilevelCacheBackend
 */
class MultilevelCacheBackend extends AbstractBackend implements BackendInterface, TaggableBackendInterface, TransientBackendInterface
{
    /**
     * Array of cache backends and their configurations
     *
     * @var array
     */
    protected $backends = [];

    /**
     * Name of original cache configuration this multi-level backend wraps
     *
     * @var string|null
     */
    protected $original;

    /**
     * @param string|null $original
     * @return void
     */
    public function setOriginal($original)
    {
        $this->original = $original;
    }

    /**
     * @param array $backends
     * @return void
     */
    public function setBackends(array $backends)
    {
        $this->backends = [];
        foreach ($backends as $cacheConfiguration) {
            $className = $cacheConfiguration['backend'] ?? Typo3DatabaseBackend::class;
            $options = $cacheConfiguration['options'] ?? [];
            $backend = new $className($this->context, $options);
            if (is_callable([$backend, 'initializeObject'])) {
                $backend->initializeObject();
            }
            $this->backends[] = [
                'instance' => $backend,
                'options' => $options,
                'multilevel' => $cacheConfiguration['multilevel'] ?? []
            ];
        }
    }

    /**
     * @param FrontendInterface $cache
     * @return void
     */
    public function setCache(FrontendInterface $cache)
    {
        $this->cache = $cache;
        $this->cacheIdentifier = $this->original;
        foreach ($this->backends as $backend) {
            $backend['instance']->setCache($cache);
        }
    }


    /**
     * @param string $entryIdentifier
     * @param mixed $data
     * @param array $tags
     * @param integer|null $lifetime
     * @return void
     */
    public function set($entryIdentifier, $data, array $tags = [], $lifetime = null)
    {
        foreach ($this->backends as $backend) {
            if ($backend['multilevel']['cascade'] ?? true) {
                $this->delegatedSet($backend, $entryIdentifier, $data, $tags, $lifetime);
            }
        }
    }

    /**
     * @param string $entryIdentifier
     * @return mixed
     */
    public function get($entryIdentifier)
    {
        $final = false;
        $delegates = [];
        foreach ($this->backends as $backend) {
            $result = $backend['instance']->get($this->delegatedIdentifier($backend, $entryIdentifier));
            if ($result !== false) {
                $final = $result;
                if (!$backend['instance'] instanceof TransientBackendInterface) {
                    $final = unserialize($final);
                }
                break;
            } elseif ($backend['multilevel']['cascade'] ?? true) {
                $delegates[] = $backend;
            }
        }
        if ($final !== false) {
            foreach ($delegates as $delegate) {
                $this->delegatedSet($delegate, $entryIdentifier, $final);
            }
        }
        return $final;
    }

    /**
     * @param string $entryIdentifier
     * @return boolean
     */
    public function has($entryIdentifier)
    {
        foreach ($this->backends as $backend) {
            if ($backend['instance']->has($this->delegatedIdentifier($backend, $entryIdentifier))) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $entryIdentifier
     * @return void
     */
    public function remove($entryIdentifier)
    {
        foreach ($this->backends as $backend) {
            if ($backend['multilevel']['cascade'] ?? true) {
                $backend['instance']->remove($this->delegatedIdentifier($backend, $entryIdentifier));
            }
        }
    }

    /**
     * @return void
     */
    public function flush()
    {
        foreach ($this->backends as $backend) {
            if ($backend['multilevel']['flush'] ?? true) {
                $backend['instance']->flush();
            }
        }
    }

    /**
     * @param string $tag
     * @return void
     */
    public function flushByTag($tag)
    {
        foreach ($this->backends as $backend) {
            if ($backend['multilevel']['flush'] ?? true) {
                if ($backend['instance'] instanceof TaggableBackendInterface) {
                    $backend['instance']->flushByTag($tag);
                } else {
                    $backend['instance']->flush();
                }
            }
        }
    }

    /**
     * @param string $tag
     * @return array
     */
    public function findIdentifiersByTag($tag)
    {
        $identifiers = [];
        foreach ($this->backends as $backend) {
            if ($backend['instance'] instanceof TaggableBackendInterface) {
                $subIdentifiers = $backend['instance']->findIdentifiersByTag($tag);
                $prefix = strlen($backend['multilevel']['prefix'] ?? '');
                if ($prefix) {
                    foreach ($subIdentifiers as &$subIdentifier) {
                        $subIdentifier = substr($subIdentifier, 0, $prefix);
                    }
                }
                $identifiers += $subIdentifiers;
            }
        }
        return array_unique($identifiers);
    }

    /**
     * @return void
     */
    public function collectGarbage()
    {
        foreach ($this->backends as $backend) {
            $backend['instance']->collectGarbage();
        }
    }

    /**
     * Returns the needed schema for nested backends.
     *
     * @return string
     */
    public function getTableDefinitions()
    {
        $tableDefinitions = '';
        foreach ($this->backends as $backendConfig) {
            $backend = $backendConfig['instance'];
            if (method_exists($backend, 'getTableDefinitions')) {
                $tableDefinitions .= LF . $backend->getTableDefinitions();
            }
        }

        return $tableDefinitions;
    }

    /**
     * @param array $delegate
     * @param string $entryIdentifier
     * @return string
     */
    protected function delegatedIdentifier(array $delegate, $entryIdentifier)
    {
        if (isset($delegate['multilevel']['prefix'])) {
            $entryIdentifier = $delegate['multilevel']['prefix'] . $entryIdentifier;
        }
        return $entryIdentifier;
    }

    /**
     * @param array $delegate
     * @param $entryIdentifier
     * @param $data
     * @param array $tags
     * @param null $lifetime
     */
    protected function delegatedSet(array $delegate, $entryIdentifier, $data, array $tags = [], $lifetime = null)
    {
        if (!$delegate['instance'] instanceof TransientBackendInterface) {
            $data = serialize($data);
        }
        $delegate['instance']->set($this->delegatedIdentifier($delegate, $entryIdentifier), $data, $tags, $lifetime);
    }

}
