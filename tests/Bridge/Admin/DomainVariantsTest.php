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

use Austral\DomainBundle\Services\DomainsManagement;
use Austral\DomainBundle\Tests\Fixtures\Admin\Entity\Article;

/**
 * The test module "articles" (entity filtered by domain): a list by domain (DomainVariantProvider).
 */
final class DomainVariantsTest extends AdminWebTestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    $this->login('root');
  }

  public function testWithoutDomainTheChoiceIsSkipped(): void
  {
    $this->client->request('GET', '/austral-admin/articles');

    self::assertResponseRedirects('/austral-admin/articles/master');
    $this->client->request('GET', '/austral-admin/articles/master');
    self::assertResponseIsSuccessful();
  }

  public function testWithOneDomainTheChoiceIsSkipped(): void
  {
    $this->domain('Main site', 'main');

    $this->client->request('GET', '/austral-admin/articles');

    self::assertResponseRedirects('/austral-admin/articles/main', null, 'One domain: no "all the domains" either.');
  }

  public function testATileByDomain(): void
  {
    $this->domain('Main site', 'main');
    $this->domain('Other site', 'other', true, false, 1);
    $this->domain('Virtual site', 'virtual', false, true, 2);

    $crawler = $this->client->request('GET', '/austral-admin/articles');

    self::assertResponseIsSuccessful();
    self::assertSame(
      ['/austral-admin/articles/main', '/austral-admin/articles/other', '/austral-admin/articles/'.DomainsManagement::DOMAIN_ID_FOR_ALL_DOMAINS],
      $crawler->filter('.austral-admin-tiles a')->each(static fn ($link): string => (string) $link->attr('href')),
    );
    self::assertStringContainsString('All the domains', $crawler->filter('.austral-admin-tiles')->text());
  }

  public function testTheListOfADomainShowsItsArticles(): void
  {
    $main = $this->domain('Main site', 'main');
    $other = $this->domain('Other site', 'other', true, false, 1);
    $this->article('Article of main', $main->getId());
    $this->article('Article saved without domain', DomainsManagement::DOMAIN_ID_MASTER);
    $this->article('Article of other', $other->getId());
    $this->article('Article of all', DomainsManagement::DOMAIN_ID_FOR_ALL_DOMAINS);

    $list = $this->client->request('GET', '/austral-admin/articles/main')->filter('tbody')->text();
    self::assertStringContainsString('Article of main', $list);
    self::assertStringContainsString('Article saved without domain', $list, 'The master domain shows the entities of "master".');
    self::assertStringNotContainsString('Article of other', $list);
    self::assertStringNotContainsString('Article of all', $list);

    $list = $this->client->request('GET', '/austral-admin/articles/other')->filter('tbody')->text();
    self::assertStringContainsString('Article of other', $list);
    self::assertStringNotContainsString('Article saved without domain', $list);

    $list = $this->client->request('GET', '/austral-admin/articles/'.DomainsManagement::DOMAIN_ID_FOR_ALL_DOMAINS)->filter('tbody')->text();
    self::assertStringContainsString('Article of all', $list);
    self::assertStringNotContainsString('Article of main', $list);
  }

  public function testAnArticleOfAnotherDomainIsNotFound(): void
  {
    $main = $this->domain('Main site', 'main');
    $this->domain('Other site', 'other', true, false, 1);
    $article = $this->article('Article of main', $main->getId());

    $this->client->request('GET', "/austral-admin/articles/other/{$article->getId()}/edit");
    self::assertResponseStatusCodeSame(404);
    $this->client->request('GET', '/austral-admin/articles/unknown');
    self::assertResponseStatusCodeSame(404);
  }

  public function testAnArticleCreatedInADomainBelongsToIt(): void
  {
    $this->domain('Main site', 'main');
    $other = $this->domain('Other site', 'other', true, false, 1);

    $crawler = $this->client->request('GET', '/austral-admin/articles/other/new');
    self::assertResponseIsSuccessful();
    $this->client->submit($crawler->filter('form[name="form"]')->form(['form[title]' => 'New article']));

    self::assertResponseRedirects();
    $article = $this->doctrine()->getRepository(Article::class)->findOneBy(['title' => 'New article']);
    self::assertInstanceOf(Article::class, $article);
    self::assertSame($other->getId(), $article->getDomainId());
  }

  public function testTheDomainOfTheListIsTheFilterDomainOfTheRequest(): void
  {
    $this->domain('Main site', 'main');
    $other = $this->domain('Other site', 'other', true, false, 1);

    $this->client->request('GET', '/austral-admin/articles/other');

    self::assertResponseIsSuccessful();
    self::assertSame($other->getId(), $this->domainsManagement()->getFilterDomainId());
  }

  public function testTheKeyOfAVariantIsTheIdWhenTheKeynameIsNotASegment(): void
  {
    $this->domain('Main site', 'main');
    $other = $this->domain('Other site', 'other.site', true, false, 1);

    $crawler = $this->client->request('GET', '/austral-admin/articles');

    self::assertCount(1, $crawler->filter("a[href=\"/austral-admin/articles/{$other->getId()}\"]"));
  }
}
