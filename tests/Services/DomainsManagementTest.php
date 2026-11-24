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

namespace Austral\DomainBundle\Tests\Services;

use Austral\CacheBundle\Cache\ResultCache;
use Austral\DomainBundle\EntityManager\DomainEntityManager;
use Austral\DomainBundle\Services\DomainsManagement;
use Austral\DomainBundle\Tests\Fixtures\TestDomain;
use Austral\HttpBundle\Configuration\HttpConfiguration;
use Austral\HttpBundle\Services\HttpRequest;
use Austral\ToolsBundle\Services\Debug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class DomainsManagementTest extends TestCase
{
  /**
   * Enabled domains of the database, indexed by host like DomainRepository::selectAllEnabledDomains().
   *
   * @var array<string, TestDomain>
   */
  private array $database = [];

  /**
   * Number of queries of the enabled domains.
   */
  private int $loads = 0;

  private RequestStack $requestStack;

  /**
   * @var HttpRequest<object>
   */
  private HttpRequest $httpRequest;

  protected function setUp(): void
  {
    $this->requestStack = new RequestStack();
    $this->httpRequest = new HttpRequest(new HttpConfiguration());
    $this->httpRequest->setLanguage('fr');
  }

  public function testTheDomainsAreLoadedOnFirstUseOnly(): void
  {
    $master = TestDomain::create('m-id', 'www.a.test', true);
    $this->database($master);
    $management = $this->management();
    self::assertSame(0, $this->loads);

    self::assertSame(['www.a.test' => $master], $management->getDomains());
    $management->getDomainMaster();
    $management->getDomainById('m-id');
    $management->getCurrentDomain();

    self::assertSame(1, $this->loads);
  }

  public function testTheMasterDomainOfTheDatabase(): void
  {
    $master = TestDomain::create('m-id', 'www.a.test', true);
    $other = TestDomain::create('b-id', 'www.b.test');
    $this->database($master, $other);
    $management = $this->management();

    self::assertSame($master, $management->getDomainMaster());
    self::assertSame($master, $management->getDomainById(DomainsManagement::DOMAIN_ID_MASTER));
    self::assertSame('m-id', $management->getDomainIdByKeyname(DomainsManagement::DOMAIN_ID_MASTER));
    self::assertSame('m-id', $management->getFilterDomainId());
    self::assertSame($master, $management->getFilterDomain());
    self::assertSame(['m-id' => $master, 'b-id' => $other], $management->getDomainsWithoutVirtual());
    self::assertFalse($management->isMultiDomain());
  }

  public function testAVirtualMasterIsCreatedWhenNoDomainOfTheDatabaseIsTheMaster(): void
  {
    $other = TestDomain::create('b-id', 'www.b.test');
    $this->database($other);
    $this->request('www.b.test');
    $management = $this->management();

    $master = $management->getDomainMaster();
    self::assertNotNull($master);
    self::assertSame(DomainsManagement::DOMAIN_ID_MASTER, $master->getId());
    self::assertTrue($master->getIsVirtual());
    self::assertTrue($master->getIsMaster());
    self::assertSame('www.b.test', $master->getDomain());
    self::assertSame(DomainsManagement::DOMAIN_ID_MASTER, $management->getFilterDomainId());
    self::assertSame(['b-id' => $other, DomainsManagement::DOMAIN_ID_MASTER => $master], $management->getDomainsWithoutVirtual());
    // Every request is on the virtual master; the domain of the host stays available
    self::assertSame($master, $management->getCurrentDomain());
    self::assertSame($other, $management->getCurrentDomain(false));
  }

  public function testTheDomainForAllDomainsIsVirtual(): void
  {
    $this->database(TestDomain::create('m-id', 'www.a.test', true));
    $management = $this->management();

    $forAll = $management->getDomainForAll();
    self::assertNotNull($forAll);
    self::assertSame(DomainsManagement::DOMAIN_ID_FOR_ALL_DOMAINS, $forAll->getId());
    self::assertTrue($forAll->getIsVirtual());
    self::assertFalse($forAll->getIsMaster());
    self::assertArrayNotHasKey(DomainsManagement::DOMAIN_ID_FOR_ALL_DOMAINS, $management->getDomainsWithoutVirtual());
  }

  public function testADomainClassThatIsNotADomainIsRejected(): void
  {
    $entityManager = $this->createStub(DomainEntityManager::class);
    $entityManager->method('getClass')->willReturn(\stdClass::class);
    $management = new DomainsManagement($entityManager, $this->requestStack, $this->httpRequest, new Debug());

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('austral.entity.domain.class');

    $management->getDomains();
  }

  public function testTheCurrentDomainIsDetectedFromTheHostOfTheMainRequest(): void
  {
    $master = TestDomain::create('m-id', 'www.a.test', true);
    $other = TestDomain::create('b-id', 'www.b.test');
    $this->database($master, $other);
    $this->request('www.b.test');
    $management = $this->management();

    self::assertSame($other, $management->getCurrentDomain());
    self::assertSame($other, $management->getCurrentDomain(false));

    // A sub-request does not change it, a given request does
    $this->request('www.a.test');
    $management->resolveCurrentDomain();
    self::assertSame($other, $management->getCurrentDomain());
    $management->resolveCurrentDomain(Request::create('https://www.a.test/'));
    self::assertSame($master, $management->getCurrentDomain());
  }

  public function testThereIsNoCurrentDomainForAnUnknownHostOrWithoutRequest(): void
  {
    $this->database(TestDomain::create('m-id', 'www.a.test', true));
    self::assertNull($this->management()->getCurrentDomain());

    $this->request('www.unknown.test');
    $management = $this->management();
    self::assertNull($management->getCurrentDomain());
    self::assertNull($management->getCurrentDomain(false));
  }

  public function testAVirtualDomainIsResolvedToItsMaster(): void
  {
    $master = TestDomain::create('m-id', 'www.a.test', true);
    $alias = TestDomain::create('v-id', 'alias.a.test');
    $alias->setIsVirtual(true);
    $alias->setMaster($master);
    $this->database($master, $alias);
    $this->request('alias.a.test');
    $management = $this->management();

    self::assertSame($master, $management->getCurrentDomain());
    self::assertSame($alias, $management->getCurrentDomain(false));
    self::assertArrayNotHasKey('v-id', $management->getDomainsWithoutVirtual());
  }

  public function testTheCurrentDomainCanBeSet(): void
  {
    $other = TestDomain::create('b-id', 'www.b.test');
    $this->database(TestDomain::create('m-id', 'www.a.test', true), $other);
    $management = $this->management();

    $management->setCurrentDomain($other);

    self::assertSame($other, $management->getCurrentDomain());
  }

  public function testSeveralMastersMakeAMultiDomainSite(): void
  {
    $this->database(TestDomain::create('a-id', 'www.a.test', true), TestDomain::create('b-id', 'www.b.test', true));

    self::assertTrue($this->management()->isMultiDomain());
  }

  public function testAVirtualOrRedirectedMasterIsNotCounted(): void
  {
    $redirect = TestDomain::create('r-id', 'old.a.test', true);
    $redirect->setRedirectUrl('https://www.a.test');
    $virtual = TestDomain::create('v-id', 'alias.a.test', true);
    $virtual->setIsVirtual(true);
    $this->database(TestDomain::create('a-id', 'www.a.test', true), $redirect, $virtual);

    self::assertFalse($this->management()->isMultiDomain());
  }

  public function testADomainIsFoundByIdOrKeyname(): void
  {
    $shop = TestDomain::create('s-id', 'shop.a.test', false, 'shop');
    $this->database(TestDomain::create('m-id', 'www.a.test', true), $shop);
    $this->request('shop.a.test');
    $management = $this->management();

    self::assertSame($shop, $management->getDomainById('s-id'));
    self::assertSame($shop, $management->getDomainById('shop'));
    self::assertSame($shop, $management->getDomainByKeyname('shop'));
    self::assertSame('s-id', $management->getDomainIdByKeyname('shop'));
    self::assertNull($management->getDomainById('unknown'));
    self::assertNull($management->getDomainByKeyname('unknown'));
    self::assertNull($management->getDomainIdByKeyname(null));
    // Without id: the current domain
    self::assertSame($shop, $management->getDomainById());
    self::assertSame($shop, $management->getDomainById(''));
  }

  public function testTheMasterIsTheFallbackOfGetDomainByIdOrMaster(): void
  {
    $master = TestDomain::create('m-id', 'www.a.test', true);
    $other = TestDomain::create('b-id', 'www.b.test');
    $this->database($master, $other);
    $management = $this->management();

    self::assertSame($other, $management->getDomainByIdOrMaster('b-id'));
    self::assertSame($master, $management->getDomainByIdOrMaster('unknown'));
    // No request (console): no current domain
    self::assertSame($master, $management->getDomainByIdOrMaster());
  }

  public function testTheIdOfADomainIsResolved(): void
  {
    $this->database(TestDomain::create('m-id', 'www.a.test', true), TestDomain::create('s-id', 'shop.a.test', false, 'shop'));
    $this->request('shop.a.test');
    $management = $this->management();

    self::assertSame('m-id', $management->resolveDomainId());
    self::assertSame('m-id', $management->resolveDomainId(null));
    self::assertSame('m-id', $management->resolveDomainId(DomainsManagement::DOMAIN_ID_MASTER));
    self::assertSame('s-id', $management->resolveDomainId('shop'));
    self::assertSame('s-id', $management->resolveDomainId(DomainsManagement::DOMAIN_ID_CURRENT));
    // An explicit id is kept as it is
    self::assertSame('s-id', $management->resolveDomainId('s-id'));
    self::assertSame('unknown', $management->resolveDomainId('unknown'));
  }

  public function testTheCurrentDomainIdIsTheMasterWithoutCurrentDomain(): void
  {
    $this->database(TestDomain::create('m-id', 'www.a.test', true));

    self::assertSame('m-id', $this->management()->resolveDomainId(DomainsManagement::DOMAIN_ID_CURRENT));
  }

  public function testTheFilterDomainSetBeforeTheLoadingIsKept(): void
  {
    $other = TestDomain::create('b-id', 'www.b.test');
    $this->database(TestDomain::create('m-id', 'www.a.test', true), $other);
    $management = $this->management();

    $management->setFilterDomainId('b-id');

    self::assertSame('b-id', $management->getFilterDomainId());
    self::assertSame($other, $management->getFilterDomain());
  }

  public function testTheLanguageOfTheRequestIsSetOnEveryDomain(): void
  {
    $master = TestDomain::create('m-id', 'www.a.test', true);
    $other = TestDomain::create('b-id', 'www.b.test');
    $this->database($master, $other);
    $management = $this->management();
    $forAll = $management->getDomainForAll();
    self::assertNotNull($forAll);

    foreach ([$master, $other, $forAll] as $domain)
    {
      self::assertSame('fr', $domain->getRequestLanguage());
    }

    $management->applyRequestLanguage('en');
    foreach ([$master, $other, $forAll] as $domain)
    {
      self::assertSame('en', $domain->getRequestLanguage());
    }
  }

  public function testTheCurrentLanguageIsTheLanguageOfTheDomainOrOfTheRequest(): void
  {
    $master = TestDomain::create('m-id', 'www.a.test', true);
    $master->setLanguage('en');
    $this->database($master);

    self::assertSame('fr', $this->management()->getCurrentLanguage());

    $this->request('www.a.test');
    self::assertSame('en', $this->management()->getCurrentLanguage());
  }

  public function testTheRequestContextOfADomain(): void
  {
    $other = TestDomain::create('b-id', 'www.b.test');
    $other->setScheme('http');
    $redirect = TestDomain::create('r-id', 'old.a.test');
    $redirect->setRedirectUrl('https://www.a.test');
    $this->database(TestDomain::create('m-id', 'www.a.test', true), $other, $redirect);
    $this->request('www.a.test');
    $management = $this->management();

    $context = $management->getRequestContextByDomainId('b-id');
    self::assertNotNull($context);
    self::assertSame('http', $context->getScheme());
    self::assertSame('www.b.test', $context->getHost());

    $context = $management->getRequestContextByDomainId(DomainsManagement::DOMAIN_ID_MASTER);
    self::assertNotNull($context);
    self::assertSame('https', $context->getScheme());
    self::assertSame('www.a.test', $context->getHost());

    self::assertNull($management->getRequestContextByDomainId('r-id'));
    self::assertNull($management->getRequestContextByDomainId('unknown'));
  }

  public function testRefreshReloadsTheDomainsAndDetectsTheCurrentDomainAgain(): void
  {
    $master = TestDomain::create('m-id', 'www.a.test', true);
    $this->database($master);
    $this->request('www.b.test');
    $management = $this->management();
    $management->setFilterDomainId('b-id');
    self::assertNull($management->getCurrentDomain());

    $other = TestDomain::create('b-id', 'www.b.test');
    $this->database($master, $other);
    $management->refresh();

    self::assertSame(2, $this->loads);
    self::assertSame($other, $management->getCurrentDomain());
    self::assertSame('b-id', $management->getFilterDomainId());
  }

  public function testRefreshDoesNotKeepADomainSetBySetCurrentDomain(): void
  {
    $other = TestDomain::create('b-id', 'www.b.test');
    $this->database(TestDomain::create('m-id', 'www.a.test', true), $other);
    $management = $this->management();
    $management->setCurrentDomain($other);

    $management->refresh();

    self::assertNull($management->getCurrentDomain());
  }

  public function testResetForgetsEverything(): void
  {
    $this->database(TestDomain::create('m-id', 'www.a.test', true));
    $management = $this->management();
    $management->setFilterDomainId('b-id');
    $management->getDomains();

    $management->reset();

    self::assertSame('m-id', $management->getFilterDomainId());
    self::assertSame(2, $this->loads);
  }

  /**
   * @return iterable<string, array{bool}>
   */
  public static function withResultCache(): iterable
  {
    yield 'with the result cache' => [true];
    yield 'without the result cache' => [false];
  }

  #[DataProvider('withResultCache')]
  public function testTheResultCacheIsGivenToTheQueryOfTheDomains(bool $withResultCache): void
  {
    $resultCache = $withResultCache ? new ResultCache(null, 0) : null;
    $entityManager = $this->createMock(DomainEntityManager::class);
    $entityManager->method('getClass')->willReturn(TestDomain::class);
    $entityManager->expects(self::once())
      ->method('selectAllEnabledDomains')
      ->with(self::identicalTo($resultCache))
      ->willReturn([]);

    new DomainsManagement($entityManager, $this->requestStack, $this->httpRequest, new Debug(), $resultCache)->getDomains();
  }

  private function database(TestDomain ...$domains): void
  {
    $this->database = [];
    foreach ($domains as $domain)
    {
      $this->database[$domain->getDomain()] = $domain;
    }
  }

  private function request(string $host): void
  {
    $this->requestStack->push(Request::create(sprintf('https://%s/', $host)));
  }

  /**
   * @return DomainsManagement<object>
   */
  private function management(): DomainsManagement
  {
    $entityManager = $this->createStub(DomainEntityManager::class);
    $entityManager->method('getClass')->willReturn(TestDomain::class);
    $entityManager->method('selectAllEnabledDomains')->willReturnCallback(function (): array {
      ++$this->loads;

      return $this->database;
    });

    return new DomainsManagement($entityManager, $this->requestStack, $this->httpRequest, new Debug());
  }
}
