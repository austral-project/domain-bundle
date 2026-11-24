<?php

/*
 * This file is part of the Austral Domain Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\DomainBundle\Services;

use Austral\CacheBundle\Cache\ResultCache;
use Austral\Contracts\Domain\FilterByDomainInterface;
use Austral\DomainBundle\Entity\Interfaces\DomainInterface;
use Austral\DomainBundle\EntityManager\DomainEntityManager;
use Austral\HttpBundle\Services\HttpRequest;
use Austral\ToolsBundle\Services\Debug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Registry of the domains, and domain of the current request.
 *
 * The enabled domains are loaded on first use, never when the service is created: from the Doctrine result
 * cache of cache-bundle when austral_domain.cache is enabled (key CACHE_KEY_ENABLED_DOMAINS, invalidated when
 * a domain is saved), otherwise from the database.
 * The current domain is detected from the host of the main request: by RequestListener on kernel.request,
 * or on first use when no listener ran (console). reset() forgets everything between two requests
 * of a long-running worker (tag kernel.reset).
 *
 * @template T of object
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
class DomainsManagement implements ResetInterface
{
  public const string DOMAIN_ID_FOR_ALL_DOMAINS = 'for-all-domains';
  public const string DOMAIN_ID_MASTER = FilterByDomainInterface::DOMAIN_ID_MASTER;
  public const string DOMAIN_ID_CURRENT = 'current';

  /**
   * Key of the enabled domains in the Doctrine result cache of cache-bundle.
   */
  public const string CACHE_KEY_ENABLED_DOMAINS = 'austral_domain.enabled_domains';

  /**
   * @var bool
   */
  protected bool $loaded = false;

  /**
   * Enabled domains of the database, indexed by host.
   *
   * @var array<string, DomainInterface<T>>
   */
  protected array $domains = [];

  /**
   * Every known domain (database and virtual), indexed by id.
   *
   * @var array<string, DomainInterface<T>>
   */
  protected array $domainsById = [];

  /**
   * @var array<string, string>
   */
  protected array $domainsIdByKeyname = [];

  /**
   * @var array<string, DomainInterface<T>>
   */
  protected array $domainsWithoutVirtual = [];

  /**
   * @var array<string, DomainInterface<T>>
   */
  protected array $domainsMaster = [];

  /**
   * Ids of the domains that have a request context: enabled domains of the database that are not redirections.
   *
   * @var array<string, true>
   */
  protected array $requestContextDomainIds = [];

  /**
   * @var DomainInterface<T>|null
   */
  protected ?DomainInterface $domainMaster = null;

  /**
   * True when no domain of the database is the master: a virtual master domain is created.
   *
   * @var bool
   */
  protected bool $virtualDomainMaster = false;

  /**
   * @var bool
   */
  protected bool $currentDomainResolved = false;

  /**
   * @var DomainInterface<T>|null
   */
  protected ?DomainInterface $currentDomain = null;

  /**
   * @var DomainInterface<T>|null
   */
  protected ?DomainInterface $currentDomainWithVirtual = null;

  /**
   * @var string|null
   */
  protected ?string $filterDomainId = null;

  /**
   * True when the filter domain was set by setFilterDomainId(): loading the domains does not replace it.
   *
   * @var bool
   */
  protected bool $filterDomainIdDefined = false;

  /**
   * @param DomainEntityManager<T> $domainEntityManager
   * @param RequestStack           $requestStack
   * @param HttpRequest<T>         $httpRequest
   * @param Debug                  $debug
   * @param ResultCache|null       $resultCache         null when austral_domain.cache is disabled
   */
  public function __construct(
    protected DomainEntityManager $domainEntityManager,
    protected RequestStack $requestStack,
    protected HttpRequest $httpRequest,
    protected Debug $debug,
    protected ?ResultCache $resultCache = null
  ) {
  }

  /**
   * Detects the current domain from the host of the request (the main request by default).
   *
   * @param Request|null $request
   *
   * @return $this
   */
  public function resolveCurrentDomain(?Request $request = null): self
  {
    $this->load();
    $host = ($request ?? $this->requestStack->getMainRequest())?->getHost();

    $this->currentDomain = null;
    $this->currentDomainWithVirtual = null;
    if (null !== $host && array_key_exists($host, $this->domains))
    {
      $domain = $this->domains[$host];
      $this->currentDomainWithVirtual = $domain;
      $this->currentDomain = $domain->getIsVirtual() && $domain->getMaster() ? $domain->getMaster() : $domain;
    }
    if ($this->virtualDomainMaster)
    {
      $this->currentDomain = $this->domainMaster;
    }
    $this->currentDomainResolved = true;

    return $this;
  }

  /**
   * Sets the language of the request on every domain (DomainInterface::getCurrentLanguage()).
   *
   * @param string|null $language
   *
   * @return $this
   */
  public function applyRequestLanguage(?string $language): self
  {
    foreach ($this->domainsById as $domain)
    {
      $domain->setRequestLanguage($language);
    }

    return $this;
  }

  /**
   * True when the database has more than one master domain (replaces getEnabledDomainWithoutVirtual()).
   *
   * @return bool
   */
  public function isMultiDomain(): bool
  {
    $this->load();

    return count($this->domainsMaster) > 1;
  }

  /**
   * @return array<string, DomainInterface<T>>
   */
  public function getDomainsWithoutVirtual(): array
  {
    $this->load();

    return $this->domainsWithoutVirtual;
  }

  /**
   * @return array<string, DomainInterface<T>>
   */
  public function getDomains(): array
  {
    $this->load();

    return $this->domains;
  }

  /**
   * @return DomainInterface<T>|null
   */
  public function getDomainMaster(): ?DomainInterface
  {
    $this->load();

    return $this->domainMaster;
  }

  /**
   * @param bool $withoutVirtual
   *
   * @return DomainInterface<T>|null
   */
  public function getCurrentDomain(bool $withoutVirtual = true): ?DomainInterface
  {
    if (!$this->currentDomainResolved)
    {
      $this->resolveCurrentDomain();
    }

    return $withoutVirtual ? $this->currentDomain : $this->currentDomainWithVirtual;
  }

  /**
   * @param DomainInterface<T> $currentDomain
   *
   * @return $this
   */
  public function setCurrentDomain(DomainInterface $currentDomain): self
  {
    $this->load();
    $this->currentDomain = $currentDomain;
    $this->currentDomainResolved = true;

    return $this;
  }

  /**
   * @return string|null
   */
  public function getCurrentLanguage(): ?string
  {
    return $this->getCurrentDomain()?->getLanguage() ?: $this->httpRequest->getLanguage();
  }

  /**
   * Id of a domain given by id, keyname, "master" (default) or "current" (replaces getReelDomainId()).
   *
   * @param string|null $domainId
   * @param bool        $withoutVirtual
   *
   * @return string|null
   */
  public function resolveDomainId(?string $domainId = self::DOMAIN_ID_MASTER, bool $withoutVirtual = true): ?string
  {
    $this->load();
    $domainId ??= self::DOMAIN_ID_MASTER;
    if (self::DOMAIN_ID_CURRENT === $domainId)
    {
      $domainId = $this->getCurrentDomain($withoutVirtual)?->getId() ?? self::DOMAIN_ID_MASTER;
    }

    return $this->domainsIdByKeyname[$domainId] ?? $domainId;
  }

  /**
   * Domain by id or keyname; the current domain when no id is given.
   *
   * @param string|null $id
   *
   * @return DomainInterface<T>|null
   */
  public function getDomainById(?string $id = null): ?DomainInterface
  {
    $this->load();
    if (null === $id || '' === $id)
    {
      return $this->getCurrentDomain();
    }
    $id = $this->domainsIdByKeyname[$id] ?? $id;

    return $this->domainsById[$id] ?? null;
  }

  /**
   * Domain by id or keyname, falling back to the master domain when it does not exist.
   *
   * @param string|null $id
   *
   * @return DomainInterface<T>|null
   */
  public function getDomainByIdOrMaster(?string $id = null): ?DomainInterface
  {
    return $this->getDomainById($id) ?? $this->getDomainMaster();
  }

  /**
   * @param string|null $keyname
   *
   * @return DomainInterface<T>|null
   */
  public function getDomainByKeyname(?string $keyname = null): ?DomainInterface
  {
    $id = $this->getDomainIdByKeyname($keyname);

    return null !== $id ? $this->getDomainById($id) : null;
  }

  /**
   * @param string|null $keyname
   *
   * @return string|null
   */
  public function getDomainIdByKeyname(?string $keyname = null): ?string
  {
    $this->load();

    return null !== $keyname ? ($this->domainsIdByKeyname[$keyname] ?? null) : null;
  }

  /**
   * @return DomainInterface<T>|null
   */
  public function getDomainForAll(): ?DomainInterface
  {
    return $this->getDomainById(self::DOMAIN_ID_FOR_ALL_DOMAINS);
  }

  /**
   * Id of the domain used to filter the entities; the master domain unless setFilterDomainId() was called.
   *
   * @return string|null
   */
  public function getFilterDomainId(): ?string
  {
    $this->load();

    return $this->filterDomainId;
  }

  /**
   * @param string|null $filterDomainId
   *
   * @return $this
   */
  public function setFilterDomainId(?string $filterDomainId): self
  {
    $this->filterDomainId = $filterDomainId;
    $this->filterDomainIdDefined = true;

    return $this;
  }

  /**
   * @return DomainInterface<T>|null
   */
  public function getFilterDomain(): ?DomainInterface
  {
    return $this->getDomainById($this->getFilterDomainId());
  }

  /**
   * Request context of a domain (scheme and host of the domain), built from the current request.
   *
   * @param string $domainId
   * @param bool   $withoutVirtual
   *
   * @return RequestContext|null
   */
  public function getRequestContextByDomainId(string $domainId, bool $withoutVirtual = true): ?RequestContext
  {
    $id = $this->resolveDomainId($domainId, $withoutVirtual);
    if (null === $id || !array_key_exists($id, $this->requestContextDomainIds))
    {
      return null;
    }
    $domain = $this->domainsById[$id];

    $requestContext = new RequestContext();
    if ($request = $this->requestStack->getCurrentRequest())
    {
      $requestContext->fromRequest($request);
    }

    return $requestContext->setScheme($domain->getScheme())->setHost($domain->getDomain());
  }

  /**
   * Reloads the domains after a change (and stores them again in the result cache). Keeps the filter domain
   * set by setFilterDomainId(), and detects the current domain again from the request when it was already
   * detected (a domain set by setCurrentDomain() is not kept).
   *
   * @return $this
   */
  public function refresh(): self
  {
    $filterDomainId = $this->filterDomainId;
    $filterDomainIdDefined = $this->filterDomainIdDefined;
    $currentDomainResolved = $this->currentDomainResolved;

    $this->reset();
    if ($filterDomainIdDefined)
    {
      $this->setFilterDomainId($filterDomainId);
    }
    $this->load();
    if ($currentDomainResolved)
    {
      $this->resolveCurrentDomain();
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function reset(): void
  {
    $this->loaded = false;
    $this->domains = [];
    $this->domainsById = [];
    $this->domainsIdByKeyname = [];
    $this->domainsWithoutVirtual = [];
    $this->domainsMaster = [];
    $this->requestContextDomainIds = [];
    $this->domainMaster = null;
    $this->virtualDomainMaster = false;
    $this->currentDomainResolved = false;
    $this->currentDomain = null;
    $this->currentDomainWithVirtual = null;
    $this->filterDomainId = null;
    $this->filterDomainIdDefined = false;
  }

  /**
   * Loads the enabled domains, once.
   */
  protected function load(): void
  {
    if ($this->loaded)
    {
      return;
    }
    $this->loaded = true;
    $this->debug->stopWatchStart('austral.domain_management.load', 'austral.domain.management');

    $this->createVirtualDomain(self::DOMAIN_ID_FOR_ALL_DOMAINS);
    $this->domains = $this->domainEntityManager->selectAllEnabledDomains($this->resultCache);
    foreach ($this->domains as $domain)
    {
      $this->domainsById[$domain->getId()] = $domain;
      if (!$this->domainMaster && $domain->getIsMaster())
      {
        $this->domainMaster = $domain;
        $this->domainsIdByKeyname[self::DOMAIN_ID_MASTER] = $domain->getId();
      }
      if (!$domain->getIsRedirect())
      {
        if (!$domain->getIsVirtual())
        {
          $this->domainsWithoutVirtual[$domain->getId()] = $domain;
          if ($domain->getIsMaster())
          {
            $this->domainsMaster[$domain->getId()] = $domain;
          }
        }
        if (null !== $domain->getKeyname())
        {
          $this->domainsIdByKeyname[$domain->getKeyname()] = $domain->getId();
        }
        $this->requestContextDomainIds[$domain->getId()] = true;
      }
    }

    if (!$this->domainMaster)
    {
      $domainMaster = $this->createVirtualDomain(self::DOMAIN_ID_MASTER);
      $domainMaster->setIsMaster(true);
      $this->domainMaster = $domainMaster;
      $this->domainsWithoutVirtual[$domainMaster->getId()] = $domainMaster;
      $this->virtualDomainMaster = true;
    }
    if (!$this->filterDomainIdDefined)
    {
      $this->filterDomainId = $this->domainMaster->getId();
    }
    $this->applyRequestLanguage($this->httpRequest->getLanguage());

    $this->debug->stopWatchStop('austral.domain_management.load');
  }

  /**
   * @param string $keyname
   *
   * @return DomainInterface<T>
   */
  protected function createVirtualDomain(string $keyname): DomainInterface
  {
    $domainClass = $this->domainEntityManager->getClass();
    if (null === $domainClass || !is_a($domainClass, DomainInterface::class, true))
    {
      throw new \LogicException(sprintf('The domain class "%s" must implement "%s" (parameter "austral.entity.domain.class").', $domainClass ?? 'null', DomainInterface::class));
    }

    /** @var DomainInterface<T> $domain */
    $domain = new $domainClass();
    $domain->setId($keyname);
    $domain->setName($keyname);
    $domain->setKeyname($keyname);
    $domain->setDomain($this->requestStack->getMainRequest()?->getHost() ?? '');
    $domain->setIsVirtual(true);
    $this->domainsById[$keyname] = $domain;
    $this->domainsIdByKeyname[$keyname] = $keyname;

    return $domain;
  }
}
