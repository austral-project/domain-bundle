<?php

/*
 * This file is part of the Austral Domain Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\DomainBundle\EntityManager;

use Austral\DomainBundle\Entity\Interfaces\DomainInterface;
use Austral\DomainBundle\Repository\DomainRepository;
use Austral\EntityBundle\EntityManager\EntityManager;
use Doctrine\ORM\Query\QueryException;

/**
 * Austral Domain EntityManager.
 *
 * @template T of object
 *
 * @extends EntityManager<T>>
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class DomainEntityManager extends EntityManager
{
  /**
   * @param class-string<T>|null $entity
   *
   * @return DomainRepository<T>|null
   */
  public function getRepository(?string $entity = null): ?DomainRepository
  {
    /** @var DomainRepository<T>|null $repository */
    $repository = $this->repository;

    return $repository;
  }

  /**
   * @param array<string, mixed> $values
   *
   * @return DomainInterface<T>
   */
  public function create(array $values = []): DomainInterface
  {
    /** @var DomainInterface<T> $object */
    $object = parent::create($values);

    return $object;
  }

  /**
   * @param string $host
   *
   * @return DomainInterface<T>|null
   *
   * @throws \Doctrine\ORM\NonUniqueResultException
   */
  public function retrieveByDomain(string $host): ?DomainInterface
  {
    return $this->getRepository()?->retrieveByDomain($host);
  }

  /**
   * @return DomainInterface<T>|null
   *
   * @throws \Doctrine\ORM\NonUniqueResultException
   */
  public function retrieveByMaster(): ?DomainInterface
  {
    return $this->getRepository()?->retrieveByMaster();
  }

  /**
   * @return array<string, DomainInterface<T>>
   *
   * @throws QueryException
   */
  public function selectAllEnabledDomains(): array
  {
    return $this->getRepository()?->selectAllEnabledDomains() ?? [];
  }
}
