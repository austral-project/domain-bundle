<?php

/*
 * This file is part of the Austral Domain Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Austral\DomainBundle\Tests\Listener;

use Austral\CacheBundle\Event\CacheInvalidatedEvent;
use Austral\CacheBundle\Event\CacheRebuildEvent;
use Austral\DomainBundle\Listener\CacheListener;
use Austral\DomainBundle\Services\DomainsManagement;
use Austral\DomainBundle\Tests\Fixtures\TestDomain;
use Austral\DomainBundle\Tests\Fixtures\TestFilteredEntity;
use PHPUnit\Framework\TestCase;

final class CacheListenerTest extends TestCase
{
  public function testTheDomainsAreReloadedWhenADomainChanged(): void
  {
    $this->listener(reloads: 1)->onInvalidated(new CacheInvalidatedEvent([TestDomain::class], [], []));
  }

  public function testTheDomainsAreNotReloadedWhenAnotherEntityChanged(): void
  {
    $this->listener(reloads: 0)->onInvalidated(new CacheInvalidatedEvent([TestFilteredEntity::class], [], []));
  }

  public function testTheDomainsAreReloadedWhenTheResultsAreRebuilt(): void
  {
    $this->listener(reloads: 1)->onRebuild(new CacheRebuildEvent(result: true, render: false));
  }

  public function testTheDomainsAreNotReloadedWhenOnlyTheRendersAreRebuilt(): void
  {
    $this->listener(reloads: 0)->onRebuild(new CacheRebuildEvent(result: false, render: true));
  }

  /**
   * @return CacheListener<object>
   */
  private function listener(int $reloads): CacheListener
  {
    $domainsManagement = $this->createMock(DomainsManagement::class);
    $domainsManagement->expects(self::exactly($reloads))->method('refresh')->willReturnSelf();

    return new CacheListener($domainsManagement);
  }
}
