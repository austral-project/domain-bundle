<?php

/*
 * This file is part of the Austral Domain Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\DomainBundle\Cache;

use Austral\CacheBundle\Cache\CacheContextProviderInterface;
use Austral\DomainBundle\Services\DomainsManagement;

/**
 * Adds the current domain to the key of the renders of cache-bundle: each domain has its own render.
 *
 * @template T of object
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class DomainCacheContext implements CacheContextProviderInterface
{
  /**
   * @param DomainsManagement<T> $domainsManagement
   */
  public function __construct(protected DomainsManagement $domainsManagement)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContext(): array
  {
    return ['domain' => $this->domainsManagement->getCurrentDomain()?->getId()];
  }
}
