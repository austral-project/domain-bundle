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

namespace Austral\DomainBundle\Tests\Fixtures\Admin\Entity;

use Austral\DomainBundle\Entity\Domain as BaseDomain;
use Austral\DomainBundle\Repository\DomainRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Domain of the test application, as in the Skeleton of the bundle.
 *
 * @extends BaseDomain<object>
 */
#[ORM\Table(name: 'austral_http_domain')]
#[ORM\Entity(repositoryClass: DomainRepository::class)]
class Domain extends BaseDomain
{
}
