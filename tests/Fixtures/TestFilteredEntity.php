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

namespace Austral\DomainBundle\Tests\Fixtures;

use Austral\Contracts\Domain\FilterByDomainInterface;
use Austral\DomainBundle\Entity\Traits\FilterByDomainTrait;
use Austral\EntityBundle\Entity\Entity;

/**
 * Entity filtered by domain, with children (getChildren()) that are attached with it.
 *
 * @extends Entity<object>
 *
 * @implements FilterByDomainInterface<object>
 */
final class TestFilteredEntity extends Entity implements FilterByDomainInterface
{
  /** @use FilterByDomainTrait<object> */
  use FilterByDomainTrait;

  /**
   * @var list<object>
   */
  private array $children = [];

  /**
   * @return list<object>
   */
  public function getChildren(): array
  {
    return $this->children;
  }

  public function addChild(object $child): self
  {
    $this->children[] = $child;

    return $this;
  }
}
