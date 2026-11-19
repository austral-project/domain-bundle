# Austral Domain Bundle

[![Latest Stable Version](https://img.shields.io/packagist/v/austral/domain-bundle.svg?style=flat-square)](https://packagist.org/packages/austral/domain-bundle)
[![CI](https://github.com/austral-project/domain-bundle/actions/workflows/ci.yml/badge.svg?branch=4.x)](https://github.com/austral-project/domain-bundle/actions/workflows/ci.yml?query=branch%3A4.x)
[![PHP](https://img.shields.io/packagist/php-v/austral/domain-bundle.svg?style=flat-square)](https://packagist.org/packages/austral/domain-bundle)
[![Symfony](https://img.shields.io/badge/Symfony-7.4-black?logo=symfony&style=flat-square)](https://symfony.com/releases/7.4)
[![License](https://img.shields.io/github/license/austral-project/domain-bundle.svg?style=flat-square)](https://github.com/austral-project/domain-bundle/blob/4.x/LICENSE)

Domain management and request context initialization for multi-domain Austral applications.

> **Work in progress:** Austral Domain Bundle is under active development. Its public API and configuration may change before the first stable release.

## Overview

Austral Domain Bundle provides the domain management layer for Austral applications. It owns domain entities and filtering, and initializes the current domain context for each request.

Its responsibilities include:

- managing configured domains and their persistence;
- resolving and initializing the current domain before request routing;
- initializing the domain context lazily in CLI commands;
- resetting request-specific state in long-running workers;
- providing an optional administration integration when Austral Admin Bundle is installed.

The domain listener runs before Symfony's router so that the current domain is available while the request is handled.

## Architecture

This package is independent from `http-bundle`. Neither bundle depends on the other:

- `domain-bundle` owns domain management and current-domain initialization;
- `http-bundle` provides the HTTP controller and event foundations.

The existing `austral_http_domain` database table is retained to avoid moving domain data during the bundle separation.

## Requirements

- PHP `>=8.4 <9.0`
- Symfony `^7.4`
- Austral `4.x`

## Installation

Install the package with Composer:

```bash
composer require austral/domain-bundle
```

If your application does not enable Austral bundles automatically, register `Austral\DomainBundle\DomainBundle` in `config/bundles.php`.

## Administration

The administration screens and dashboard integration are provided through the bundle's admin bridge. They are loaded only when Austral Admin Bundle is installed.

## Credits

Austral Domain Bundle is part of the [Austral framework](https://austral.dev/), maintained by [Austral](https://github.com/austral-project).

## License

See the [LICENSE](LICENSE) file for details.
