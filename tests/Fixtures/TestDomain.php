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

use Austral\DomainBundle\Entity\Domain;

/**
 * Domain entity of the application: Domain is a mapped superclass.
 *
 * @extends Domain<object>
 */
final class TestDomain extends Domain
{
  /**
   * Enabled domain, as loaded from the database.
   */
  public static function create(string $id, string $host, bool $isMaster = false, ?string $keyname = null): self
  {
    $domain = new self();
    $domain->setId($id);
    $domain->setDomain($host);
    $domain->setIsMaster($isMaster);
    $domain->setIsEnabled(true);
    if (null !== $keyname)
    {
      $domain->setKeyname($keyname);
    }

    return $domain;
  }
}
