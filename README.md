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
- Authentication endpoints (via AuthController)
- User management endpoints (via UserController)
- Hello endpoint (via HelloController)

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
