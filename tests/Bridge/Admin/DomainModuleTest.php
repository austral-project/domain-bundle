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

use Austral\DomainBundle\Tests\Fixtures\Admin\Entity\Domain;
use Austral\DomainBundle\Tests\Fixtures\Admin\Entity\Group;
use Austral\DomainBundle\Tests\Fixtures\Admin\Entity\Role;
use Austral\DomainBundle\Tests\Fixtures\Admin\Entity\User;

/**
 * The module "domain" of the bridge (DomainAdmin) and the block of the dashboard.
 */
final class DomainModuleTest extends AdminWebTestCase
{
  public function testRootSeesAllTheDomains(): void
  {
    $this->domain('Main site', 'main');
    $this->domain('English site', 'english', false);
    $this->login('root');

    $crawler = $this->client->request('GET', '/austral-admin/domain');

    self::assertResponseIsSuccessful();
    $list = $crawler->filter('tbody')->text();
    self::assertStringContainsString('Main site', $list);
    self::assertStringContainsString('English site', $list);
  }

  public function testAUserWhoIsNotRootSeesTheMasterDomainsOnly(): void
  {
    $this->domain('Main site', 'main');
    $english = $this->domain('English site', 'english', false);
    $this->grant('admin', ['ROLE_DOMAIN', 'ROLE_DOMAIN_EDIT', 'ROLE_DOMAIN_CREATE']);
    $this->login('admin');

    $crawler = $this->client->request('GET', '/austral-admin/domain');
    self::assertResponseIsSuccessful();
    $list = $crawler->filter('tbody')->text();
    self::assertStringContainsString('Main site', $list);
    self::assertStringNotContainsString('English site', $list);

    $this->client->request('GET', "/austral-admin/domain/{$english->getId()}/edit");
    self::assertResponseStatusCodeSame(404);

    $crawler = $this->client->request('GET', '/austral-admin/domain/new');
    self::assertResponseIsSuccessful();
    self::assertCount(0, $crawler->filter('[name="form[keyname]"]'), 'The key is for root only.');
  }

  public function testCreateADomain(): void
  {
    $this->login('root');

    $crawler = $this->client->request('GET', '/austral-admin/domain/new');
    self::assertResponseIsSuccessful();
    self::assertSame(['prod', 'preprod'], $crawler->filter('select[name="form[domainEnv]"] option')->each(static fn ($option): string => (string) $option->attr('value')));

    $this->client->submit($crawler->filter('form[name="form"]')->form([
      'form[name]' => 'Main site',
      'form[domain]' => 'www.example.com',
      'form[scheme]' => 'https',
      'form[domainEnv]' => 'preprod',
    ]));

    self::assertResponseRedirects();
    $domain = $this->doctrine()->getRepository(Domain::class)->findOneBy(['domain' => 'www.example.com']);
    self::assertInstanceOf(Domain::class, $domain);
    self::assertSame('Main site', $domain->getName());
    self::assertSame('main-site', $domain->getKeyname(), 'A domain without key gets its name (as a key).');
    self::assertSame('preprod', $domain->getDomainEnv());
  }

  public function testTheConfigurationKeysAreSaved(): void
  {
    $domain = $this->domain('Main site', 'main');
    $this->login('root');

    $crawler = $this->client->request('GET', "/austral-admin/domain/{$domain->getId()}/edit");
    self::assertResponseIsSuccessful();
    $form = $crawler->filter('form[name="form"]')->form();
    $form['form[seoTitleSuffix]'] = '- Main';
    $this->client->submit($form);

    self::assertResponseRedirects();
    $this->doctrine()->clear();
    $domain = $this->doctrine()->getRepository(Domain::class)->find($domain->getId());
    self::assertInstanceOf(Domain::class, $domain);
    self::assertSame('- Main', $domain->getConfigKey('seoTitleSuffix'));
    self::assertSame('main', $domain->getKeyname(), 'The key is kept.');
  }

  public function testTheDashboardCountsTheDomainsOfAMultiDomainSite(): void
  {
    $this->login('root');
    $this->domain('Main site', 'main');

    $crawler = $this->client->request('GET', '/austral-admin/');
    self::assertResponseIsSuccessful();
    self::assertCount(0, $crawler->filter('.austral-admin-dashboard-block--domains'), 'One master domain: no block.');

    $this->domain('Other site', 'other');
    $crawler = $this->client->request('GET', '/austral-admin/');
    self::assertCount(1, $crawler->filter('.austral-admin-dashboard-block--domains'));
    self::assertStringContainsString('2', $crawler->filter('.austral-admin-dashboard-block--domains')->text());
  }

  /**
   * @param list<string> $roles
   */
  private function grant(string $username, array $roles): void
  {
    $user = $this->doctrine()->getRepository(User::class)->findOneBy(['username' => $username]);
    self::assertInstanceOf(User::class, $user);
    $group = new Group();
    $group->setName('Domains')->setKeyname('domains');
    foreach ($roles as $name)
    {
      $role = new Role();
      $role->setRole($name)->setName($name);
      $this->doctrine()->persist($role);
      $group->addRole($role);
    }
    $this->doctrine()->persist($group);
    $user->addGroup($group);
    $this->doctrine()->flush();
  }
}
