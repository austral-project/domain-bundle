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

use Austral\DomainBundle\Listener\EntityManagerListener;
use Austral\DomainBundle\Mapping\DomainFilterMapping;
use Austral\DomainBundle\Services\DomainsManagement;
use Austral\DomainBundle\Tests\Fixtures\TestDomain;
use Austral\DomainBundle\Tests\Fixtures\TestFilteredEntity;
use Austral\EntityBundle\EntityManager\EntityManager;
use Austral\EntityBundle\Event\EntityManagerEvent;
use Austral\EntityBundle\Mapping\Mapping;
use Austral\HttpBundle\Configuration\HttpConfiguration;
use Austral\HttpBundle\Services\HttpRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class EntityManagerListenerTest extends TestCase
{
  /**
   * @var DomainFilterMapping<object>
   */
  private DomainFilterMapping $domainFilter;

  protected function setUp(): void
  {
    $this->domainFilter = new DomainFilterMapping();
    $this->domainFilter->setAutoDomainId(true);
  }

  public function testADuplicateInTheModuleOfTheMasterIsAttachedToTheMaster(): void
  {
    self::assertSame('m-id', $this->duplicate(['domainId' => 'm-id'])->getDomainId());
  }

  public function testTheIdOfTheDomainIsStoredWhenTheRouteGivesItsKeyname(): void
  {
    self::assertSame('m-id', $this->duplicate(['domainId' => DomainsManagement::DOMAIN_ID_MASTER])->getDomainId());
  }

  /**
   * @return iterable<string, array{array<string, string>}>
   */
  public static function unchangedDuplicates(): iterable
  {
    yield 'domain that is not the master' => [['domainId' => 'b-id']];
    yield 'unknown domain' => [['domainId' => 'unknown']];
    yield 'no domain in the route' => [[]];
  }

  /**
   * @param array<string, string> $routeParams
   */
  #[DataProvider('unchangedDuplicates')]
  public function testTheDomainOfTheDuplicateIsNotChanged(array $routeParams): void
  {
    self::assertNull($this->duplicate($routeParams)->getDomainId());
  }

  public function testNothingChangesWithoutAutoDomainId(): void
  {
    $this->domainFilter->setAutoDomainId(false);

    self::assertNull($this->duplicate(['domainId' => 'm-id'])->getDomainId());
  }

  public function testNothingChangesWithoutSourceObject(): void
  {
    self::assertNull($this->duplicate(['domainId' => 'm-id'], withSourceObject: false)->getDomainId());
  }

  /**
   * Duplicates an entity from an admin route with these parameters; returns the duplicate.
   *
   * @param array<string, string> $routeParams
   */
  private function duplicate(array $routeParams, bool $withSourceObject = true): TestFilteredEntity
  {
    $master = TestDomain::create('m-id', 'www.a.test', true);
    $domains = [
      'm-id' => $master,
      DomainsManagement::DOMAIN_ID_MASTER => $master,
      'b-id' => TestDomain::create('b-id', 'www.b.test'),
    ];
    $domainsManagement = $this->createStub(DomainsManagement::class);
    $domainsManagement->method('getDomainById')->willReturnCallback(fn (?string $id = null): ?TestDomain => $domains[$id ?? ''] ?? null);

    $mapping = $this->createStub(Mapping::class);
    $mapping->method('getEntityClassMapping')->willReturnCallback(fn (string $class): ?DomainFilterMapping => TestFilteredEntity::class === $class ? $this->domainFilter : null);

    $request = Request::create('https://www.a.test/admin');
    $request->attributes->set('_route_params', $routeParams);
    $httpRequest = new HttpRequest(new HttpConfiguration());
    $httpRequest->setRequest($request);

    $duplicate = new TestFilteredEntity();
    $event = new EntityManagerEvent(EntityManagerEvent::EVENT_DUPLICATE, $this->createStub(EntityManager::class), $duplicate);
    if ($withSourceObject)
    {
      $event->setSourceObject(new TestFilteredEntity());
    }

    new EntityManagerListener($mapping, $domainsManagement, $httpRequest)->duplicate($event);

    return $duplicate;
  }
}
