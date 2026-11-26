# Upgrade from 3.1 to 4.0

## Requirements

- PHP 8.4 minimum
- Symfony 7.4
- Doctrine ORM 3
- austral/cache-bundle 4.0

## Update the dependencies

```diff
+ "austral/domain-bundle": "^4.0"
```

All `austral/*` packages must be upgraded to `^4.0` at the same time. In 3.x, the domains were managed by http-bundle.

domain-bundle does not require `doctrine/doctrine-migrations-bundle`: a project that runs migrations requires it itself, in the version it chooses (`symfony/orm-pack` installs the 4.x).

Register the bundle, and cache-bundle on which it depends, in `config/bundles.php`:

```php
return [
    // ...
    Austral\CacheBundle\AustralCacheBundle::class => ['all' => true],
    Austral\DomainBundle\AustralDomainBundle::class => ['all' => true],
];
```

## Breaking changes

### New bundle, extracted from http-bundle

domain-bundle does not exist in 3.x: the domain management was part of http-bundle. Start with the section "Domain management moved to `austral/domain-bundle`" of the `UPGRADE-4.0.md` of http-bundle: namespaces, entity of the application, migration command. The sections below describe the changes of the API.

### Services

The services are defined explicitly and their ids are the class names, private. Inject them through the constructor of your services; only the public aliases can be fetched from the container.

| 3.1 (http-bundle) | 4.0 |
|-------------------|-----|
| `austral.http.domains.management` | `austral.domain.management` (public alias of `Austral\DomainBundle\Services\DomainsManagement`) |
| `austral.entity_manager.domain` | unchanged (public alias of `Austral\DomainBundle\EntityManager\DomainEntityManager`) |
| `austral.http.doctrine.listener`, `austral.http.entity_mapping.listener`, `austral.http.entity_manager.listener` | removed: the listeners are only called by the dispatchers |
| `austral.http.domain_attachement_migrater.command` | removed: the command keeps its name, `austral:domain:migrate` |

```diff
- $container->get('austral.http.domains.management')
+ $container->get('austral.domain.management')
```

### `DomainsManagement`

The domains are loaded on first use. The current domain is detected from the host of the main request by `Listener\RequestListener`, on `kernel.request` with priority 40 (before the router), or on first use in a command. Nothing has to be initialised any more.

| 3.1 | 4.0 |
|-----|-----|
| `initialize(bool $detectAutoHost = true)` | removed: nothing to call. `resolveCurrentDomain(?Request $request = null)` detects the current domain again; `refresh()` reloads the domains after a change |
| `getHost()` | removed: `$requestStack->getMainRequest()?->getHost()` |
| `getHttpRequest()` | removed: inject `austral.http.request` |
| `getEnabledDomainWithoutVirtual()` | `isMultiDomain()` |
| `getReelDomainId(?string $domainId, bool $withoutVirtual)` | `resolveDomainId(?string $domainId, bool $withoutVirtual)` |
| `objectDomainAttachement(EntityInterface $object, bool $withChild = true)` | `DomainAttachment::attach(EntityInterface $object, bool $withChildren = true)` |
| `getObjectsDomainAttachement()` | `DomainAttachment::pullAttachedObjects()`, which also forgets the returned entities |
| `objectAttachementByDomainId($object, ?string $domainId)` | removed: `$object->getDomainId() === $domainsManagement->resolveDomainId($domainId)` |

The constructor changes: `(DomainEntityManager, RequestStack, HttpRequest, Debug, ?ResultCache)` instead of `(Mapping, DomainEntityManager, HttpRequest, Debug)`. It only matters if you extend the class.

`resolveDomainId('current')` returns the master domain when there is no current domain (3.1 failed). `getRequestContextByDomainId()` builds the context from the current request when it is called, not when the domains are loaded.

New: `getDomainByIdOrMaster()` returns the master domain when the domain does not exist; `getDomainById()` still returns `null`.

### Attachment of the entities to a domain

`Services\DomainAttachment` attaches an entity filtered by domain (`#[DomainFilter(autoDomainId: true)]`, `autoAttachement` enabled) that has no domain yet to the filter domain (`DomainsManagement::getFilterDomainId()`, the master domain by default), with its children (`getChildren()`, `getChildrenEntities()`). A translation is attached through its master.

```diff
- $domainsManagement->objectDomainAttachement($page);
+ $domainAttachment->attach($page);
```

Inject `Austral\DomainBundle\Services\DomainAttachment` (no public alias).

### Doctrine listener

`Listener\DoctrineListener` is a Doctrine event listener on `onFlush`, on every connection. In 3.1, it was a subscriber on `postLoad`, `prePersist`, `preUpdate`, `postRemove` and `postFlush`, on the `default` connection only, and the attached entities were saved by a second flush. The new and updated entities are now attached in the same flush. You have nothing to change, unless you relied on the second flush.

### Cache of the domains

The enabled domains are kept in the Doctrine result cache of cache-bundle, and reloaded when a domain is saved. A change made outside Doctrine (SQL, DQL bulk update) is not seen: run `php bin/console austral:cache:rebuild --result` after it. To disable the cache of the domains only:

```yaml
# config/packages/austral_domain.yaml
austral_domain:
  cache: false
```

When the cache of HTML renders of cache-bundle is used, the current domain is part of the key of the renders: each domain has its own render.

### Entity

`Domain::getName()` returns a string, empty when the domain has no name (`?string` in 3.1).

### Configuration

The XML namespace of the bundle configuration is `https://austral.dev/schema/dic/austral_domain`.

### Administration (admin-bundle 4)

The bridge is written for admin-bundle 4:

| 3.1 | 4.0 |
|-----|-----|
| parameter `austral.admin.modules.http_bundle.domain.enabled: false` | `austral_admin: { modules: { domain: { enabled: false } } }` |
| parameter `austral.admin.class.domain.class` (Admin of the application) | `austral_admin: { modules: { domain: { class: App\Admin\DomainAdmin } } }` |
| `Austral\HttpBundle\Admin\DomainAdmin` | `Austral\DomainBundle\Bridge\Admin\DomainAdmin` |
| `Austral\HttpBundle\Listener\DashboardListener` (service `austral.http.dashboard.listener`) | `Austral\DomainBundle\Bridge\Admin\DashboardListener` |
| a module cloned by domain, `/pages/www.example.com` | a variant of the module, `/pages/{key of the domain}` (its id when the key cannot be a segment of a URL) |

The route of the module (`/austral-admin/domain`) and its roles (`ROLE_DOMAIN`, `ROLE_DOMAIN_EDIT`...) do not change. The bookmarks of the lists by domain must be updated: the segment is the key of the domain, no longer its host. A user who is not root can no longer open a domain that is not a master domain.
