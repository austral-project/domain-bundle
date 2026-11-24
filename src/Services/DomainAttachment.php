<?php

/*
 * This file is part of the Austral Domain Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\DomainBundle\Services;

use Austral\Contracts\Domain\FilterByDomainInterface;
use Austral\Contracts\Entity\EntityInterface;
use Austral\Contracts\Translate\TranslateChildInterface;
use Austral\DomainBundle\Mapping\DomainFilterMapping;
use Austral\EntityBundle\Mapping\Mapping;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Attaches the entities filtered by domain to the filter domain (DomainsManagement::getFilterDomainId()).
 *
 * An entity is attached when its mapping enables autoDomainId and autoAttachement and it has no domain yet;
 * a translation is attached through its master. Its children (getChildren(), getChildrenEntities()) are
 * attached too. The attached entities are kept until pullAttachedObjects(), so that the Doctrine listener
 * recomputes their changeset.
 *
 * @template T of object
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class DomainAttachment implements ResetInterface
{
  /**
   * @var array<int, FilterByDomainInterface<T>>
   */
  protected array $attachedObjects = [];

  /**
   * @param Mapping<T>           $mapping
   * @param DomainsManagement<T> $domainsManagement
   */
  public function __construct(
    protected Mapping $mapping,
    protected DomainsManagement $domainsManagement
  ) {
  }

  /**
   * @param EntityInterface<T> $object
   * @param bool               $withChildren
   *
   * @return $this
   */
  public function attach(EntityInterface $object, bool $withChildren = true): self
  {
    $visited = [];
    $this->attachObject($object, $withChildren, $visited);

    return $this;
  }

  /**
   * Returns the entities attached since the last call, and forgets them.
   *
   * @return list<FilterByDomainInterface<T>>
   */
  public function pullAttachedObjects(): array
  {
    $objects = array_values($this->attachedObjects);
    $this->attachedObjects = [];

    return $objects;
  }

  /**
   * {@inheritdoc}
   */
  public function reset(): void
  {
    $this->attachedObjects = [];
  }

  /**
   * @param object           $object
   * @param bool             $withChildren
   * @param array<int, true> $visited      objects already handled during this call (guards against cycles)
   *
   * @return void
   */
  protected function attachObject(object $object, bool $withChildren, array &$visited): void
  {
    if ($object instanceof TranslateChildInterface)
    {
      $object = $object->getMaster();
    }
    if (!$object instanceof FilterByDomainInterface)
    {
      return;
    }
    $objectId = spl_object_id($object);
    if (array_key_exists($objectId, $visited))
    {
      return;
    }
    $visited[$objectId] = true;

    $domainFilterMapping = $this->mapping->getEntityClassMapping($object->getClassnameForMapping(), DomainFilterMapping::class);
    if (!$domainFilterMapping instanceof DomainFilterMapping)
    {
      return;
    }

    $filterDomainId = $this->domainsManagement->getFilterDomainId();
    if ($domainFilterMapping->getAutoDomainId() && $domainFilterMapping->getAutoAttachement() && $filterDomainId && !$object->getDomainId())
    {
      $object->setDomainId($filterDomainId);
    }
    if ($withChildren)
    {
      foreach ($this->childrenOf($object) as $child)
      {
        if (is_object($child))
        {
          $this->attachObject($child, true, $visited);
        }
      }
    }
    $this->attachedObjects[$objectId] = $object;
  }

  /**
   * @param object $object
   *
   * @return iterable<mixed>
   */
  protected function childrenOf(object $object): iterable
  {
    foreach (['getChildren', 'getChildrenEntities'] as $method)
    {
      if (method_exists($object, $method))
      {
        $children = $object->{$method}();
        if (is_iterable($children))
        {
          foreach ($children as $child)
          {
            yield $child;
          }
        }
      }
    }
  }
}
