# Changelog

All notable changes to this bundle are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html). All Austral bundles share the same major and minor version.

## [Unreleased]

### Added

- First version of `austral/domain-bundle`: the domain management extracted from http-bundle 3.1, which no longer depends on it.
  - `Domain` entity, `DomainInterface`, `FilterByDomainTrait` and the `App\Entity\Austral\DomainBundle\Domain` skeleton (table `austral_http_domain`, unchanged).
  - `DomainFilter` PHP attribute and `DomainFilterMapping`.
  - `DomainsManagement`, `DomainEntityManager` and `DomainRepository`.
  - Doctrine, entity manager and entity mapping listeners.
  - `austral:domain:migrate` command.
  - Administration screen and dashboard block in `src/Bridge/Admin/`, loaded only when admin-bundle is installed.

### Changed

### Fixed

### Removed

[Unreleased]: https://github.com/austral-project/domain-bundle/commits/4.x
