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

namespace Austral\DomainBundle\Tests\Fixtures\Admin;

use Austral\DomainBundle\Attribute\DomainFilter;

/**
 * Filtered by several domains (getDomainIds()), without a domain id: no variant.
 */
#[DomainFilter(autoDomainId: false, forAllDomainEnabled: true)]
final class SharedContent
{
}
