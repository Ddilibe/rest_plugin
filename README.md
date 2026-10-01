# My REST API

A modular REST API plugin for WordPress with JWT authentication.

## Description

This plugin provides a modular REST API for WordPress, featuring JWT-based authentication, rate limiting, and Swagger documentation. It includes controllers for authentication, users, and a hello endpoint.

## Features

- **Modular Architecture**: Organized into separate controllers, routes, middleware, and utilities.
- **JWT Authentication**: Secure token-based authentication.
- **Rate Limiting**: Prevents abuse with configurable rate limits.
- **Swagger Documentation**: API documentation available at `/wp-json/cison/v1/docs`.
- **PSR-4 Autoloading**: Clean namespace structure.

## Installation

1. Download the plugin files.
2. Upload the entire `rest_api` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. (Optional) Run `composer install` if you have Composer to use autoloading.

## Usage

### API Endpoints

All endpoints are prefixed with `/wp-json/cison/v1/`.

- `GET /docs` - Retrieve Swagger JSON documentation.
- `POST /auth/api-key` - Issue a JWT (see [docs/authentication.md](docs/authentication.md)).
- `POST /cert/add-2025-preconference` - Record a 2025 pre-conference registration (see
  [docs/endpoints/add-2025-preconference.md](docs/endpoints/add-2025-preconference.md)).
- User, product, certificate, transaction and data endpoints.

## Documentation

Full reference documentation lives in [`docs/`](docs/README.md):

| Document | Contents |
| --- | --- |
| [docs/architecture.md](docs/architecture.md) | Bootstrap, autoloading, route registration, BuddyBoss bypass |
| [docs/authentication.md](docs/authentication.md) | JWT issuing and validation, the allow-list |
| [docs/configuration.md](docs/configuration.md) | Constants, environment variables, deployment checklist |
| [docs/api-reference.md](docs/api-reference.md) | Every registered endpoint |
| [docs/endpoints/add-2025-preconference.md](docs/endpoints/add-2025-preconference.md) | Detailed reference for the pre-conference write endpoint |
| [docs/data-model.md](docs/data-model.md) | Tables, xprofile field IDs, fee resolution |
| [docs/known-issues.md](docs/known-issues.md) | Defects and caveats, by severity |

### Authentication

Use JWT tokens for authenticated requests. Obtain a token via the auth endpoints.

### Configuration

Configure settings in the `src/Config/Config.php` file.

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- (Optional) Composer for dependency management

## License

This plugin is licensed under the GPL v2. See the LICENSE file for details.

## Contributing

Contributions are welcome. Please ensure code follows PSR-4 standards and includes appropriate tests.

## Changelog

### 1.0.0
- Initial release with basic REST API functionality.# rest_plugin
