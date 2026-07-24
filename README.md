<div align="center">
    <h1>Login Audit</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/iresis/login-audit"><img src="https://img.shields.io/packagist/v/iresis/login-audit.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/iresis/login-audit"><img src="https://img.shields.io/packagist/php-v/iresis/login-audit.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/iresis/login-audit"><img src="https://badge.laravel.cloud/badge/iresis/login-audit?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/iresis/login-audit/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/iresis/login-audit/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/iresis/login-audit"><img src="https://img.shields.io/packagist/dt/iresis/login-audit.svg?style=flat-square" alt="Total Downloads"></a>
</p>



## Installation

You can install the package via Composer:

```bash
composer require iresis/login-audit
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="login-audit"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="login-audit-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="login-audit-migrations"
php artisan migrate
```

## Usage

<!-- Add a basic usage example here. -->

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Login Audit! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Alexander](https://github.com/iresis)
- [All Contributors](../../contributors)

## License

Login Audit is open-sourced software licensed under the [MIT license](LICENSE.md).
