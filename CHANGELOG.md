# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.9.0]
### Added
- Support for Symfony 7.4
- Support for `psr/log` 3

### Changed
- The Symfony components the bundle uses directly are required explicitly: `symfony/config`, `symfony/dependency-injection`,
  `symfony/http-foundation`, `symfony/http-kernel`, `symfony/property-access`, `symfony/routing` and `symfony/security-core`
- `Configuration::getConfigTreeBuilder()` declares its `TreeBuilder` return type. Breaking for subclasses that override it
  without the return type: add `: TreeBuilder` to the override
- Loading a route whose controller still uses the bundle's docblock annotations (`@Body`, `@Query`, `@PathAttribute`,
  `@ResponseNormalization`, `@RequiredPermissions`, `@Validation`, `@BodyContentType`) fails with a
  `ConfigurationException` naming them whenever no annotation reader is available: on Symfony 7, and on Symfony 6.4
  with `framework.annotations` disabled. Use the attributes of the same name. Breaking for applications without an
  annotation reader: the bundle reads the docblocks of each routed class and method with Doctrine's `AnnotationReader`,
  so a docblock Doctrine cannot parse, such as an unimported annotation or an unknown tag like `@note`, fails the route
  load with Doctrine's `AnnotationException` (fix the docblock or list the tag in `@IgnoreAnnotation`), and
  `opcache.save_comments` must stay on. With an annotation reader, Symfony 3.4 to 6.4 are unchanged
- Optional parameters are declared nullable explicitly (`?Type $parameter = null`), as PHP 8.4 expects
- CI runs the tests on Symfony 7 with PHP 8.2 and 8.3

### Fixed
- On Symfony 7.1 and later, `LocaleListener` picks the locale from `Accept-Language` the same way as on older Symfony
  versions: a request asking for German (`de`) kept the default locale there

## [1.8.2]
### Changed
- CI allows packages with security advisories, so Symfony 3.4 and 4.4 jobs can install dependencies with Composer 2.10

### Fixed
- Unsupported `sort` field on paginated endpoints now returns `400 invalid_parameters` instead of `500 internal_server_error`

## [1.8.1]
### Added
- `void` typehint to `PayseraApiBundle::build` method to fix the deprecation message
- PHP 8.3 to CI
### Fixed
- `Call to a member function getClassAnnotations() on null` error in `RoutingAttributeLoader` on Symfony 6

## [1.8.0]
### Added
- Support for PHP 8 attributes
- Support for `doctrine/annotations: ^2.0`

## [1.7.0]
### Added
- Support for Symfony 6.4

### Changed
- Fixed code style 
- Fixed tests

## [1.6.0]
### Added
- Allow library to work with various version of `paysera/lib-object-wrapper` from `0.1.0`

## [1.5.1]
### Changed
- Changed error handling signatures to work with new versions.

## [1.5.0]
### Changed
- `psr/log` bumped to `^2.0`

## [1.4.0]
### Added
- Support for Symfony 5.4

### Changed
- `symfony/framework-bundle` bumped to `^5.4`
- `symfony/security-bundle` bumped to `^5.4`
- `symfony/validator` bumped to `^5.4`
- `symfony/yaml` bumped to `^5.4`

## [1.3.1]
### Changed
- Updated the paysera/lib-normalization library.

## [1.3.0]
### Added
- support for PHP 8
- `doctrine/persistence` added

### Changed
- `paysera/lib-normalization-bundle` bumped to `^1.1.0`
- `paysera/lib-normalization` bumped to `^1.2.0`
- `paysera/lib-dependency-injection` bumped to `^1.3.0`
- `doctrine/doctrine-bundle` bumped to `^1.4|^2.0`
- `doctrine/orm` bumped to `^2.5.14|^2.6`

### Removed
- support for PHP 7.0
- `paysera/lib-php-cs-fixer-config`
- `doctrine/common`



## [1.2.0]
### Changed
- `paysera_api.listener.locale` listener priority changed.

## [1.1.0]
### Changed
- When using annotations, `RestRequestOptions` will now be available after `kernel.request` event instead of `kernel.controller` one. This allows to show proper REST errors when exceptions are raised in the firewall.

## [1.0.0]
### Changed
- `ErrorNormalizer` does not return keys with `null` values anymore.
This means that you'll get errors like this:

```json
{"error":"invalid_request","error_description":"Expected non-empty request body"}
```

instead of this:

```json
{"error":"invalid_request","error_description":"Expected non-empty request body","error_uri":null,"error_properties":null,"error_data":null,"errors":null}
```

## 0.2.1
### Changed
- `paysera/lib-pagination` bumped to `^1.0`

## 0.2.0
### Changed
- since `paysera/lib-normalization` version `1.0` null keys are not filtered.

[1.0.0]: https://github.com/paysera/lib-api-bundle/compare/v0.2.1...v1.0.0
