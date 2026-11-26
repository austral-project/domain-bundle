# Austral Domain Bundle

[![Version](https://img.shields.io/packagist/v/austral/domain-bundle?label=version)](https://packagist.org/packages/austral/domain-bundle)
[![CI](https://github.com/austral-project/domain-bundle/actions/workflows/ci.yml/badge.svg?branch=4.x)](https://github.com/austral-project/domain-bundle/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/austral/domain-bundle/php?version=4.x-dev)](https://packagist.org/packages/austral/domain-bundle)
[![Symfony](https://img.shields.io/badge/symfony-7.4-black)](https://symfony.com/releases/7.4)
[![License](https://img.shields.io/github/license/austral-project/domain-bundle)](LICENSE)

Multi-domain management for Austral: the domains of the site, the domain of the current request, and the entities filtered by domain.

> This bundle is part of [Austral](https://github.com/austral-project), a modular back office and CMS for Symfony.

## Requirements

- PHP 8.4 or higher
- Symfony 7.4
- Doctrine ORM 3
- [austral/cache-bundle](https://github.com/austral-project/cache-bundle) 4.x, which keeps the domains in cache

## Installation

```bash
composer require austral/domain-bundle
```

With the [Austral recipes](https://github.com/austral-project/recipes), Flex registers the bundle, copies
`config/packages/austral_domain.yaml` and the entity `src/Entity/Austral/DomainBundle/Domain.php`, then displays the
next steps (database schema, `austral:domain:migrate`).

If you do not use Symfony Flex, register the bundle and cache-bundle in `config/bundles.php`:

```php
return [
    // ...
    Austral\CacheBundle\AustralCacheBundle::class => ['all' => true],
    Austral\DomainBundle\AustralDomainBundle::class => ['all' => true],
];
```

Without Flex, create the domain entity of the application from the skeleton `src/Skeleton/Entity/Domain.php` (`App\Entity\Austral\DomainBundle\Domain`, table `austral_http_domain`), then update the database schema.

## Configuration

```yaml
# config/packages/austral_domain.yaml
austral_domain:
  cache: true   # keep the enabled domains in the Doctrine result cache of cache-bundle
```

The cache of the domains also needs `austral_cache.enabled` (see cache-bundle).

## Usage

### Domains and current domain

`DomainsManagement` (`austral.domain.management`) loads the enabled domains on first use. On each main request, the current domain is detected from the host, before the router. A virtual domain (an alias of a domain) resolves to its master domain. When no domain of the database is the master, a virtual master domain is created.

```php
use Austral\DomainBundle\Services\DomainsManagement;

final class Menu
{
  public function __construct(private DomainsManagement $domainsManagement)
  {
  }

  public function build(string $domainId): void
  {
    $current = $this->domainsManagement->getCurrentDomain();            // null on an unknown host, unless the master is virtual
    $master = $this->domainsManagement->getDomainMaster();
    $shop = $this->domainsManagement->getDomainById('shop');            // by id or keyname, null if unknown
    $domain = $this->domainsManagement->getDomainByIdOrMaster($domainId); // the master domain if unknown
    $id = $this->domainsManagement->resolveDomainId('current');         // id of an id, keyname, "master" or "current"
    $isMultiDomain = $this->domainsManagement->isMultiDomain();         // more than one master domain
  }
}
```

In a command, there is no request: set the domain to process with `setCurrentDomain()`.

### Entities filtered by domain

```php
use Austral\Contracts\Domain\FilterByDomainInterface;
use Austral\DomainBundle\Attribute\DomainFilter;
use Austral\DomainBundle\Entity\Traits\FilterByDomainTrait;

#[DomainFilter(autoDomainId: true)]
class Page extends Entity implements FilterByDomainInterface
{
  use FilterByDomainTrait;
}
```

| Option | Default | Effect |
|--------|---------|--------|
| `autoDomainId` | `false` | The entity belongs to one domain, stored in `domainId`. |
| `autoAttachement` | `true` | With `autoDomainId`, a new entity without domain is attached to the filter domain (the master domain by default) when it is flushed, with its children (`getChildren()`, `getChildrenEntities()`). A translation is attached through its master. |
| `forAllDomainEnabled` | `false` | The entity can belong to all the domains (`for-all-domains`). |

Without `autoDomainId` and `forAllDomainEnabled`, the entity belongs to several domains: it must provide `getDomainIds()` and `setDomainIds()`.

### Cache

The enabled domains are kept in the Doctrine result cache of cache-bundle. When a domain is saved, the cache is deleted and the domains are reloaded right away. A change made outside Doctrine is not seen: run `php bin/console austral:cache:rebuild --result` after it.

When the cache of HTML renders of cache-bundle is used, the current domain is part of the key of the renders: each domain has its own render.

### Migration of existing entities

```bash
php bin/console austral:domain:migrate           # entities without domain
php bin/console austral:domain:migrate --force   # all the entities: replaces their domain
```

Attaches the existing entities filtered by domain (`autoDomainId`) to the master domain.

## Administration

When [austral/admin-bundle](https://github.com/austral-project/admin-bundle) 4 is installed, the bridge `src/Bridge/Admin/` adds:

- the module `domain` (`/austral-admin/domain`, roles `ROLE_DOMAIN`, `ROLE_DOMAIN_EDIT`...): the domains, their logos and their configuration. A user who is not root sees and edits the master domains only, and does not see the key of a domain;
- a list by domain for the entity modules whose entity has `#[DomainFilter(autoDomainId: true)]`: `/austral-admin/pages` shows a tile by domain, `/austral-admin/pages/{domain}` its entities, and an entity created there belongs to it. The domains are those without the virtual ones and the redirections; "All the domains" is added when there are several domains and the entity has `forAllDomainEnabled`. The segment of the URL is the key of the domain, or its id when the key cannot be a segment. With one domain, the choice is skipped. In the list of a domain, this domain is the filter domain of the request (`DomainsManagement::getFilterDomainId()`), as in the 3.1;
- the number of domains on the dashboard, on a site with several master domains.

The application changes or disables the module in `config/packages/austral_admin.yaml`:

```yaml
austral_admin:
  modules:
    domain: { enabled: false }
```

## Documentation

The full documentation is available at https://austral.dev.

## Versions

| Bundle | Symfony | PHP | Branch |
|--------|---------|-----|---------|
| 4.x    | 7.4     | ≥ 8.4 | `4.x` |

In 3.x, the domains were managed by http-bundle. See the [CHANGELOG](CHANGELOG.md) and, to upgrade from 3.1, [UPGRADE-4.0](UPGRADE-4.0.md).

## Contributing

Contributions are welcome: see the [contributing guide](https://github.com/austral-project/.github/blob/master/CONTRIBUTING.md). To report a security issue, follow the [security policy](https://github.com/austral-project/.github/blob/master/SECURITY.md).

## License

MIT, see [LICENSE](LICENSE).

## Credits

Created by [Matthieu Beurel](https://www.mbeurel.com). Sponsored by [Yipikai Studio](https://yipikai.studio).
