# Compose

[![Latest Version on Packagist](https://img.shields.io/packagist/v/compose/compose.svg?style=flat-square)](https://packagist.org/packages/compose/compose)
[![Tests](https://img.shields.io/github/actions/workflow/status/compose/compose/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/compose/compose/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/compose/compose.svg?style=flat-square)](https://packagist.org/packages/compose/compose)

Intelligent scaffolding for PHP projects.

## Installation

You can install the package via composer:

```bash
composer require compose/compose
```

## Usage

```php
$skeleton = new Compose\Compose();
echo $skeleton->echoPhrase('Hello, Compose!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Wyatt Castaneda](https://github.com/wyattcast44)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
