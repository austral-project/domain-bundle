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
use Austral\Contracts\Translate\TranslateMasterInterface;
use Austral\DomainBundle\Entity\Traits\FilterByDomainTrait;
use Austral\EntityBundle\Entity\Entity;

/**
 * Master of translations, filtered by domain. Abstract: the tests double it, only its domain matters.
 *
 * @extends Entity<object>
 *
 * @implements TranslateMasterInterface<object>
 * @implements FilterByDomainInterface<object>
 */
abstract class TestTranslatableEntity extends Entity implements TranslateMasterInterface, FilterByDomainInterface
{
  /** @use FilterByDomainTrait<object> */
  use FilterByDomainTrait;
}
