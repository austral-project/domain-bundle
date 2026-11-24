# Changelog

All notable changes to this bundle are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). All Austral bundles share the same major and minor version.

## [Unreleased]

First version of `austral/domain-bundle`: the domain management of http-bundle 3.1, extracted into its own bundle and rewritten. http-bundle no longer depends on it. The changes below are given against the domain management of http-bundle 3.1; see [UPGRADE-4.0](UPGRADE-4.0.md).

### Added

- The bundle, with the classes of http-bundle 3.1 in the `Austral\DomainBundle\` namespace:
  - `Domain` entity, `DomainInterface`, `FilterByDomainTrait` and the `App\Entity\Austral\DomainBundle\Domain` skeleton (table `austral_http_domain`, unchanged);
  - `DomainFilter` PHP attribute (an annotation in 3.1) and `DomainFilterMapping`;
  - `DomainsManagement`, `DomainEntityManager` and `DomainRepository`;
  - entity mapping and entity manager listeners;
  - `austral:domain:migrate` command;
  - administration screen and dashboard block in `src/Bridge/Admin/`, loaded only when admin-bundle is installed.
- Cache of the enabled domains in the Doctrine result cache of cache-bundle (key `austral_domain.enabled_domains`), deleted when a domain is saved. Option `austral_domain.cache` (`true` by default; also needs `austral_cache.enabled`).
- `Listener\CacheListener`: reloads the domains, and so rebuilds their cache, after a flush that changed a domain (`austral.cache.invalidated`) and on `austral.cache.rebuild` (`austral:cache:rebuild --result`).
- `Cache\DomainCacheContext`: the current domain is part of the key of the HTML renders of cache-bundle.
- `Listener\RequestListener`: detects the current domain on `kernel.request` (priority 40, before the router), then sets the language of the request on the domains (priority 20).
- `Services\DomainAttachment`: attaches the entities filtered by domain, and their children, to the filter domain.
- `DomainsManagement`:
  - `resolveCurrentDomain(?Request)`, `applyRequestLanguage(?string)`;
  - `getDomainByIdOrMaster()`: the domain by id or keyname, or the master domain when it does not exist;
  - `refresh()`: reloads the domains after a change, keeps the filter domain and detects the current domain again;
  - `reset()` (`kernel.reset`): forgets the domains and the current domain between two requests of a long-running worker.
- Tests of the container, of `DomainsManagement`, `DomainAttachment` and of the listeners.

### Changed

- **BREAKING** Requires PHP 8.4, Symfony 7.4, Doctrine ORM 3 and austral/cache-bundle.
- **BREAKING** Services are defined explicitly (no autowiring, no autoconfiguration). The service ids are the class names and are private; `austral.domain.management` (`austral.http.domains.management` in 3.1) and `austral.entity_manager.domain` are public aliases.
- **BREAKING** `DomainsManagement`:
  - the domains are loaded on first use, and the current domain is detected by `RequestListener` (or on first use, in a command): `initialize()` is removed;
  - constructor `(DomainEntityManager, RequestStack, HttpRequest, Debug, ?ResultCache)`;
  - `getEnabledDomainWithoutVirtual()` is renamed `isMultiDomain()`;
  - `getReelDomainId()` is renamed `resolveDomainId()`;
  - `getRequestContextByDomainId()` builds the context from the current request when it is called.
- **BREAKING** The attachment of the entities to a domain moved to `DomainAttachment`: `objectDomainAttachement()` is replaced by `attach()`, `getObjectsDomainAttachement()` by `pullAttachedObjects()`.
- **BREAKING** `Listener\DoctrineListener` is a Doctrine event listener on `onFlush` (a subscriber in 3.1, on every flush event, and on the `default` connection only). The new and updated entities are attached in the same flush: their changeset is recomputed, there is no second flush.
- `Domain::getName()` returns a string, empty when the domain has no name; `__toString()` and `getUrlParameterStatusName()` then return the host.
- The XML namespace of the bundle configuration is `https://austral.dev/schema/dic/austral_domain`.
- The admin bridge is loaded from the registered bundles, not from `config/bundles.php` of the application.

### Fixed

- `resolveDomainId('current')` (`getReelDomainId()` in 3.1) no longer fails without current domain: it returns the master domain.
- An entity whose children reference it again (a cycle) is attached once, instead of an infinite recursion.
- `EntityManagerListener` no longer fails when the route of a duplicate gives an unknown domain, and stores the id of the master domain when the route gives its keyname (`master`).

### Removed

- **BREAKING** `DomainsManagement::initialize()`, `getHost()`, `getHttpRequest()` and `objectAttachementByDomainId()`.
- **BREAKING** The `austral.http.domains.management`, `austral.http.doctrine.listener`, `austral.http.entity_mapping.listener`, `austral.http.entity_manager.listener` and `austral.http.domain_attachement_migrater.command` service ids.

[Unreleased]: https://github.com/austral-project/domain-bundle/commits/4.x
