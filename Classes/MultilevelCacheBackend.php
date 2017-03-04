<?php
namespace NamelessCoder\MultilevelCache;

use TYPO3\CMS\Core\Cache\Backend\AbstractBackend;
use TYPO3\CMS\Core\Cache\Backend\BackendInterface;
use TYPO3\CMS\Core\Cache\Backend\TaggableBackendInterface;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class MultilevelCacheBackend
 */
class MultilevelCacheBackend extends AbstractBackend implements BackendInterface, TaggableBackendInterface
{
    /**
     * Array of cache backends and their configurations
     *
     * @var array
     */
    protected $backends = [];

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
            $this->backends[] = [
                'instance' => GeneralUtility::makeInstance($className, $this->context, $options),
                'options' => $cacheConfiguration['options']['multilevel'] ?? []
            ];
        }
    }

    /**
     * @param FrontendInterface $cache
     * @return void
     */
    public function setCache(FrontendInterface $cache)
    {
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
            if ($backend['options']['cascade'] ?? true) {
                $backend['instance']->set($backend['options']['prefix'] ?? '' . $entryIdentifier, $data, $tags, $lifetime);
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
            $result = $backend['instance']->get($backend['options']['prefix'] ?? '' . $entryIdentifier);
            if ($result !== false) {
                $final = $result;
                break;
            } elseif ($backend['options']['cascade'] ?? true) {
                $delegates[] = $backend;
            }
        }
        if ($final !== false) {
            foreach ($delegates as $delegate) {
                $delegate['instance']->set($delegate['options']['prefix'] ?? '' . $entryIdentifier, $final);
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
            if ($backend['instance']->has($backend['options']['prefix'] ?? '' . $entryIdentifier)) {
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
            if ($backend['options']['cascade'] ?? true) {
                $backend['instance']->remove($backend['options']['prefix'] ?? '' . $entryIdentifier);
            }
        }
    }

    /**
     * @return void
     */
    public function flush()
    {
        foreach ($this->backends as $backend) {
            if ($backend['options']['flush'] ?? true) {
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
            if ($backend['options']['flush'] ?? true) {
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
                $prefix = strlen($backend['options']['prefix'] ?? '');
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

}
