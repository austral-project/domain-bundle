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

namespace Austral\DomainBundle\Tests\Cache;

use Austral\DomainBundle\Cache\DomainCacheContext;
use Austral\DomainBundle\Services\DomainsManagement;
use Austral\DomainBundle\Tests\Fixtures\TestDomain;
use PHPUnit\Framework\TestCase;

final class DomainCacheContextTest extends TestCase
{
  public function testTheCurrentDomainIsPartOfTheContext(): void
  {
    $domainsManagement = $this->createStub(DomainsManagement::class);
    $domainsManagement->method('getCurrentDomain')->willReturn(TestDomain::create('b-id', 'www.b.test'));

    self::assertSame(['domain' => 'b-id'], new DomainCacheContext($domainsManagement)->getCacheContext());
  }

  public function testTheContextHasNoDomainWithoutCurrentDomain(): void
  {
    $domainsManagement = $this->createStub(DomainsManagement::class);
    $domainsManagement->method('getCurrentDomain')->willReturn(null);

    self::assertSame(['domain' => null], new DomainCacheContext($domainsManagement)->getCacheContext());
  }
}
