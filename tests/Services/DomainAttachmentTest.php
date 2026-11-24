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

namespace Austral\DomainBundle\Tests\Services;

use Austral\Contracts\Translate\TranslateChildInterface;
use Austral\DomainBundle\Mapping\DomainFilterMapping;
use Austral\DomainBundle\Services\DomainAttachment;
use Austral\DomainBundle\Services\DomainsManagement;
use Austral\DomainBundle\Tests\Fixtures\TestDomain;
use Austral\DomainBundle\Tests\Fixtures\TestFilteredEntity;
use Austral\DomainBundle\Tests\Fixtures\TestTranslatableEntity;
use Austral\EntityBundle\Mapping\Mapping;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DomainAttachmentTest extends TestCase
{
  /**
   * Domain filter mapping by class.
   *
   * @var array<string, DomainFilterMapping<object>>
   */
  private array $mappings = [];

  private ?string $filterDomainId = 'm-id';

  protected function setUp(): void
  {
    $this->mappings = [
      TestFilteredEntity::class => self::domainFilter(),
      TestTranslatableEntity::class => self::domainFilter(),
    ];
  }

  public function testAnEntityWithoutDomainIsAttachedToTheFilterDomain(): void
  {
    $entity = new TestFilteredEntity();
    $attachment = $this->attachment();

    $attachment->attach($entity);

    self::assertSame('m-id', $entity->getDomainId());
    self::assertSame([$entity], $attachment->pullAttachedObjects());
    self::assertSame([], $attachment->pullAttachedObjects());
  }

  public function testTheDomainOfAnEntityIsKept(): void
  {
    $entity = new TestFilteredEntity();
    $entity->setDomainId('b-id');

    $this->attachment()->attach($entity);

    self::assertSame('b-id', $entity->getDomainId());
  }

  /**
   * @return iterable<string, array{bool, bool}>
   */
  public static function disabledAttachments(): iterable
  {
    yield 'autoDomainId disabled' => [false, true];
    yield 'autoAttachement disabled' => [true, false];
  }

  #[DataProvider('disabledAttachments')]
  public function testTheMappingCanDisableTheAttachment(bool $autoDomainId, bool $autoAttachement): void
  {
    $this->mappings[TestFilteredEntity::class] = self::domainFilter($autoDomainId, $autoAttachement);
    $entity = new TestFilteredEntity();

    $this->attachment()->attach($entity);

    self::assertNull($entity->getDomainId());
  }

  public function testNothingIsAttachedWithoutFilterDomain(): void
  {
    $this->filterDomainId = null;
    $entity = new TestFilteredEntity();

    $this->attachment()->attach($entity);

    self::assertNull($entity->getDomainId());
  }

  public function testAnEntityWithoutDomainFilterIsIgnored(): void
  {
    unset($this->mappings[TestFilteredEntity::class]);
    $entity = new TestFilteredEntity();
    $attachment = $this->attachment();

    $attachment->attach($entity);
    $attachment->attach(TestDomain::create('m-id', 'www.a.test', true));

    self::assertNull($entity->getDomainId());
    self::assertSame([], $attachment->pullAttachedObjects());
  }

  public function testTheChildrenAreAttached(): void
  {
    $grandChild = new TestFilteredEntity();
    $child = new TestFilteredEntity()->addChild($grandChild);
    $sibling = new TestFilteredEntity();
    $parent = new TestFilteredEntity()->addChild($child)->addChild($sibling)->addChild(new \stdClass());
    $attachment = $this->attachment();

    $attachment->attach($parent);

    $attached = $attachment->pullAttachedObjects();
    self::assertCount(4, $attached);
    foreach ([$parent, $child, $grandChild, $sibling] as $entity)
    {
      self::assertSame('m-id', $entity->getDomainId());
      self::assertContains($entity, $attached);
    }
  }

  public function testTheChildrenCanBeLeftOut(): void
  {
    $child = new TestFilteredEntity();
    $parent = new TestFilteredEntity()->addChild($child);
    $attachment = $this->attachment();

    $attachment->attach($parent, false);

    self::assertNull($child->getDomainId());
    self::assertSame([$parent], $attachment->pullAttachedObjects());
  }

  public function testACycleOfChildrenIsAttachedOnce(): void
  {
    $first = new TestFilteredEntity();
    $second = new TestFilteredEntity()->addChild($first);
    $first->addChild($second);
    $attachment = $this->attachment();

    $attachment->attach($first);

    self::assertSame('m-id', $second->getDomainId());
    self::assertCount(2, $attachment->pullAttachedObjects());
  }

  public function testATranslationIsAttachedThroughItsMaster(): void
  {
    $master = $this->createMock(TestTranslatableEntity::class);
    $master->method('getClassnameForMapping')->willReturn(TestTranslatableEntity::class);
    $master->method('getDomainId')->willReturn(null);
    $master->expects(self::once())->method('setDomainId')->with('m-id')->willReturnSelf();
    $translation = $this->createStub(TranslateChildInterface::class);
    $translation->method('getMaster')->willReturn($master);
    $attachment = $this->attachment();

    $attachment->attach($translation);

    self::assertSame([$master], $attachment->pullAttachedObjects());
  }

  public function testResetForgetsTheAttachedEntities(): void
  {
    $attachment = $this->attachment();
    $attachment->attach(new TestFilteredEntity());

    $attachment->reset();

    self::assertSame([], $attachment->pullAttachedObjects());
  }

  /**
   * @return DomainFilterMapping<object>
   */
  private static function domainFilter(bool $autoDomainId = true, bool $autoAttachement = true): DomainFilterMapping
  {
    $domainFilter = new DomainFilterMapping();
    $domainFilter->setAutoDomainId($autoDomainId);
    $domainFilter->setAutoAttachement($autoAttachement);

    return $domainFilter;
  }

  /**
   * @return DomainAttachment<object>
   */
  private function attachment(): DomainAttachment
  {
    $mapping = $this->createStub(Mapping::class);
    $mapping->method('getEntityClassMapping')->willReturnCallback(fn (string $class): ?DomainFilterMapping => $this->mappings[$class] ?? null);
    $domainsManagement = $this->createStub(DomainsManagement::class);
    $domainsManagement->method('getFilterDomainId')->willReturnCallback(fn (): ?string => $this->filterDomainId);

    return new DomainAttachment($mapping, $domainsManagement);
  }
}
