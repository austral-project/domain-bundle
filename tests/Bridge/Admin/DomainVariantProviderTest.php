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

namespace Austral\DomainBundle\Tests\Bridge\Admin;

use Austral\AdminBundle\Module\ModuleDefinition;
use Austral\DomainBundle\Bridge\Admin\DomainVariantProvider;
use Austral\DomainBundle\Tests\Fixtures\Admin\Entity\Article;
use Austral\DomainBundle\Tests\Fixtures\Admin\Entity\Domain;
use Austral\DomainBundle\Tests\Fixtures\Admin\NewsArticle;
use Austral\DomainBundle\Tests\Fixtures\Admin\SharedContent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The modules that get a variant by domain: their entity has #[DomainFilter(autoDomainId: true)].
 */
final class DomainVariantProviderTest extends TestCase
{
  /**
   * @return iterable<string, array{string|null, bool}>
   */
  public static function classes(): iterable
  {
    yield 'filtered entity' => [Article::class, true];
    yield 'attribute of the parent class' => [NewsArticle::class, true];
    yield 'without domain id' => [SharedContent::class, false];
    yield 'not filtered' => [Domain::class, false];
    yield 'unknown class' => ['App\\Entity\\Unknown', false];
    yield 'no class' => [null, false];
  }

  #[DataProvider('classes')]
  public function testSupports(?string $class, bool $supported): void
  {
    $definition = ModuleDefinition::fromArray(['key' => 'articles', 'path' => 'articles', 'entity' => $class]);

    self::assertSame($supported, DomainVariantProvider::supports($definition));
  }
}
