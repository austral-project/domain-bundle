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

use Austral\DomainBundle\Listener\DoctrineListener;
use Austral\DomainBundle\Mapping\DomainFilterMapping;
use Austral\DomainBundle\Services\DomainAttachment;
use Austral\DomainBundle\Services\DomainsManagement;
use Austral\DomainBundle\Tests\Fixtures\TestFilteredEntity;
use Austral\EntityBundle\Mapping\Mapping;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;

final class DoctrineListenerTest extends TestCase
{
  public function testTheNewAndUpdatedEntitiesAreAttachedInTheSameFlush(): void
  {
    $inserted = new TestFilteredEntity();
    $updated = new TestFilteredEntity();
    // Child of a new entity, not persisted: it has no changeset to recompute
    $child = new TestFilteredEntity();
    $inserted->addChild($child);

    /** @var list<object> $recomputed */
    $recomputed = [];
    $unitOfWork = $this->createMock(UnitOfWork::class);
    $unitOfWork->method('getScheduledEntityInsertions')->willReturn([$inserted]);
    $unitOfWork->method('getScheduledEntityUpdates')->willReturn([$updated, new \stdClass()]);
    $unitOfWork->method('isInIdentityMap')->willReturnCallback(fn (object $object): bool => $object === $updated);
    $unitOfWork->method('isScheduledForInsert')->willReturnCallback(fn (object $object): bool => $object === $inserted);
    $unitOfWork->expects(self::exactly(2))
      ->method('recomputeSingleEntityChangeSet')
      ->willReturnCallback(function (ClassMetadata $classMetadata, object $object) use (&$recomputed): void {
        $recomputed[] = $object;
      });

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
    $entityManager->method('getClassMetadata')->willReturn(new ClassMetadata(TestFilteredEntity::class));

    $attachment = $this->attachment();
    new DoctrineListener($attachment)->onFlush(new OnFlushEventArgs($entityManager));

    foreach ([$inserted, $updated, $child] as $entity)
    {
      self::assertSame('m-id', $entity->getDomainId());
    }
    self::assertCount(2, $recomputed);
    self::assertContains($inserted, $recomputed);
    self::assertContains($updated, $recomputed);
    // The listener took the attached entities: nothing is left for the next flush
    self::assertSame([], $attachment->pullAttachedObjects());
  }

  /**
   * @return DomainAttachment<object>
   */
  private function attachment(): DomainAttachment
  {
    $domainFilter = new DomainFilterMapping();
    $domainFilter->setAutoDomainId(true);
    $domainFilter->setAutoAttachement(true);
    $mapping = $this->createStub(Mapping::class);
    $mapping->method('getEntityClassMapping')->willReturnCallback(fn (string $class): ?DomainFilterMapping => TestFilteredEntity::class === $class ? $domainFilter : null);
    $domainsManagement = $this->createStub(DomainsManagement::class);
    $domainsManagement->method('getFilterDomainId')->willReturn('m-id');

    return new DomainAttachment($mapping, $domainsManagement);
  }
}
