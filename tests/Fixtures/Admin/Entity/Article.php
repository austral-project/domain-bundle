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

use Austral\Contracts\Domain\FilterByDomainInterface;
use Austral\DomainBundle\Attribute\DomainFilter;
use Austral\DomainBundle\Entity\Traits\FilterByDomainTrait;
use Austral\EntityBundle\Entity\Entity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * Entity filtered by domain of the test module "articles": a list by domain, and one for all the domains.
 *
 * @extends Entity<object>
 *
 * @implements FilterByDomainInterface<object>
 */
#[ORM\Table(name: 'test_article')]
#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[DomainFilter(autoDomainId: true, forAllDomainEnabled: true)]
class Article extends Entity implements FilterByDomainInterface, \Stringable
{
  /** @use FilterByDomainTrait<object> */
  use FilterByDomainTrait;

  #[ORM\Id]
  #[ORM\Column(type: Types::STRING, length: 40)]
  protected string $id;

  #[ORM\Column(type: Types::STRING, length: 255)]
  protected string $title = '';

  public function __construct(string $title = '', ?string $domainId = null)
  {
    parent::__construct();
    $this->id = Uuid::uuid4()->toString();
    $this->title = $title;
    $this->domainId = $domainId;
  }

  public function __toString(): string
  {
    return $this->title;
  }

  public function getId(): string
  {
    return $this->id;
  }

  public function setId(string $id): static
  {
    $this->id = $id;

    return $this;
  }

  public function getTitle(): string
  {
    return $this->title;
  }

  public function setTitle(string $title): static
  {
    $this->title = $title;

    return $this;
  }
}
