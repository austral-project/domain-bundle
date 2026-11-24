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

use Austral\Contracts\Entity\EntityInterface;
use Austral\DomainBundle\Services\DomainAttachment;
use Doctrine\ORM\Event\OnFlushEventArgs;

/**
 * Attaches the new and updated entities (and their children) to the filter domain.
 *
 * Everything is done in onFlush: the changes made to the entities are taken into account by recomputing
 * their changeset, in the same flush.
 *
 * @template T of object
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class DoctrineListener
{
  /**
   * @param DomainAttachment<T> $domainAttachment
   */
  public function __construct(protected DomainAttachment $domainAttachment)
  {
  }

  /**
   * @param OnFlushEventArgs $args
   *
   * @return void
   */
  public function onFlush(OnFlushEventArgs $args): void
  {
    $entityManager = $args->getObjectManager();
    $unitOfWork = $entityManager->getUnitOfWork();

    foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates()] as $object)
    {
      if ($object instanceof EntityInterface)
      {
        $this->domainAttachment->attach($object);
      }
    }

    foreach ($this->domainAttachment->pullAttachedObjects() as $object)
    {
      if ($unitOfWork->isInIdentityMap($object) || $unitOfWork->isScheduledForInsert($object))
      {
        $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata($object::class), $object);
      }
    }
  }
}
