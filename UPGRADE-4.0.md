# Upgrade from 3.1 to 4.0

## Requirements

- PHP 8.4 minimum
- Symfony 7.4
- Doctrine ORM 3

## Update the dependencies

```diff
+ "austral/domain-bundle": "^4.0"
```

All `austral/*` packages must be upgraded to `^4.0` at the same time. In 3.x, the domains were managed by http-bundle.

## Breaking changes

### New bundle, extracted from http-bundle

domain-bundle does not exist in 3.x: the domain management was part of http-bundle. To migrate a 3.1 project, follow the section "Domain management moved to `austral/domain-bundle`" of the `UPGRADE-4.0.md` of http-bundle (namespaces, entity of the application, migration command).

