<?php

/*
 * This file is part of the Austral Domain Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\DomainBundle\Listener;

use Austral\CacheBundle\Event\CacheInvalidatedEvent;
use Austral\CacheBundle\Event\CacheRebuildEvent;
use Austral\DomainBundle\Entity\Interfaces\DomainInterface;
use Austral\DomainBundle\Services\DomainsManagement;

/**
 * Reloads the domains when they change, so that the result cache is rebuilt right away and the next visitor
 * finds the domains ready:
 *
 * - austral.cache.invalidated: after a flush that inserted, updated or deleted a domain;
 * - austral.cache.rebuild: on demand (admin, command austral:cache:rebuild), after cache-bundle cleared the pools.
 *
 * @template T of object
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class CacheListener
{
  /**
   * @param DomainsManagement<T> $domainsManagement
   */
  public function __construct(protected DomainsManagement $domainsManagement)
  {
  }

  /**
   * @param CacheInvalidatedEvent $event
   *
   * @return void
   */
  public function onInvalidated(CacheInvalidatedEvent $event): void
  {
    if ($event->hasClass(DomainInterface::class))
    {
      $this->domainsManagement->refresh();
    }
  }

  /**
   * @param CacheRebuildEvent $event
   *
   * @return void
   */
  public function onRebuild(CacheRebuildEvent $event): void
  {
    if ($event->isResult())
    {
      $this->domainsManagement->refresh();
    }
  }
}
