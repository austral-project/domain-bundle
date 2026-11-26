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

use Austral\DomainBundle\Tests\Fixtures\Admin\Entity\Article;

/**
 * Inherits the #[DomainFilter] of Article.
 */
final class NewsArticle extends Article
{
}
