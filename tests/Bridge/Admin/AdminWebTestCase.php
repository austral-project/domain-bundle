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
use Austral\DomainBundle\Tests\Fixtures\Admin\AdminKernel;
use Austral\DomainBundle\Tests\Fixtures\Admin\Entity\Article;
use Austral\DomainBundle\Tests\Fixtures\Admin\Entity\Domain;
use Austral\DomainBundle\Tests\Fixtures\Admin\Entity\User;
use Austral\SecurityBundle\Entity\Interfaces\UserInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The test application on an empty database, with a root user and an administrator who is not root.
 */
abstract class AdminWebTestCase extends WebTestCase
{
  protected KernelBrowser $client;

  /**
   * @param array<string, mixed> $options
   */
  protected static function createKernel(array $options = []): KernelInterface
  {
    return new AdminKernel('test', false);
  }

  protected function setUp(): void
  {
    $this->client = static::createClient();
    $this->client->disableReboot();
    $doctrine = $this->doctrine();
    $schemaTool = new SchemaTool($doctrine);
    $metadata = $doctrine->getMetadataFactory()->getAllMetadata();
    $schemaTool->dropSchema($metadata);
    $schemaTool->createSchema($metadata);

    $this->user('root', UserInterface::USER_TYPE_ROOT);
    $this->user('admin', UserInterface::USER_TYPE_ADMIN);
  }

  protected function doctrine(): EntityManagerInterface
  {
    /* @var EntityManagerInterface */
    return static::getContainer()->get('doctrine.orm.entity_manager');
  }

  protected function user(string $username, string $type): User
  {
    $user = new User();
    $user->setUsername($username)->setEmail("{$username}@austral.dev")->setIsActive(true)->setTypeUser($type)->setLanguage('en');
    $user->setPassword('secret');
    $this->doctrine()->persist($user);
    $this->doctrine()->flush();

    return $user;
  }

  protected function login(string $username): void
  {
    $user = $this->doctrine()->getRepository(User::class)->findOneBy(['username' => $username]);
    self::assertInstanceOf(User::class, $user);
    $this->client->loginUser($user, 'austral_admin');
  }

  /**
   * A domain saved in the database; the domains are read again on the next request.
   */
  protected function domain(string $name, string $keyname, bool $isMaster = true, bool $isVirtual = false, int $position = 0): Domain
  {
    $domain = new Domain();
    $domain->setName($name)->setKeyname($keyname)->setDomain("{$keyname}.test")->setIsMaster($isMaster)->setIsVirtual($isVirtual)->setIsEnabled(true);
    $domain->setPosition($position);
    $this->doctrine()->persist($domain);
    $this->doctrine()->flush();
    $this->domainsManagement()->reset();

    return $domain;
  }

  protected function article(string $title, ?string $domainId): Article
  {
    $article = new Article($title, $domainId);
    $this->doctrine()->persist($article);
    $this->doctrine()->flush();

    return $article;
  }

  /**
   * @return DomainsManagement<object>
   */
  protected function domainsManagement(): DomainsManagement
  {
    $domainsManagement = static::getContainer()->get('austral.domain.management');
    self::assertInstanceOf(DomainsManagement::class, $domainsManagement);

    return $domainsManagement;
  }

  /**
   * CSRF token of the admin, read from a page of the admin.
   */
  protected function csrfToken(string $url = '/austral-admin/'): string
  {
    $crawler = $this->client->request('GET', $url);
    $meta = $crawler->filter('meta[name="austral-admin-csrf-token"]');
    self::assertGreaterThan(0, $meta->count(), "No CSRF token on {$url}.");

    return (string) $meta->first()->attr('content');
  }

  /**
   * FrameworkBundle::boot() registers the ErrorHandler of Symfony as exception handler (without symfony/runtime):
   * it is removed after the test, PHPUnit fails a test that leaves a handler (risky).
   */
  protected function tearDown(): void
  {
    parent::tearDown();
    self::restoreExceptionHandlers();
  }

  private static function restoreExceptionHandlers(): void
  {
    while (true)
    {
      $handler = set_exception_handler(static fn (\Throwable $exception) => null);
      restore_exception_handler();
      if (!\is_array($handler) || !$handler[0] instanceof ErrorHandler)
      {
        return;
      }
      restore_exception_handler();
    }
  }
}
