# Configuration

The plugin has no settings screen and no options table. Everything is configured through PHP
constants (typically in `wp-config.php`) or environment variables, resolved at runtime by
`SRC\Config\Config::get()`.

## The resolver

```php
// src/Config/Config.php
public static function get($key, $default = null)
{
    if (defined($key)) {
        return constant($key);
    }
    $env = getenv($key);
    if ($env !== false) {
        return $env;
    }
    return $default;
}
```

Precedence: **PHP constant → environment variable → default**. There is no caching, so a constant
redefined mid-request changes behaviour immediately.

## Keys consumed by the plugin

| Key | Read at | Default | Purpose |
| --- | --- | --- | --- |
| `ACCEPTED_USERS` | `AuthController.php:22`, `AuthController.php:74`, `Utils/Jwt.php:54` | `[]` | Allow-list of e-mail addresses permitted to obtain and keep a token |
| `CISON_CERT_TABLE` | `DataController.php:15` (no default), `UserController.php:16` (`''`) | `null` / `''` | Certificate table name; falls back to the literal `wprx_cison_certificates` in `CertController.php:14` |
| `SSSECURE_AUTH_KEY` | `CertificationController.php:158` | `''` | WordPress secret key used as the HMAC key for certificate-registry validation |

### `ACCEPTED_USERS`

```php
define('ACCEPTED_USERS', array('admin@example.com', 'registrar@example.com'));
```

The value is cast with `(array)` before `in_array()`. Because environment variables are always
strings, an env-var form of a single address expands to `[0 => 'a@b.com']` — which still works,
because the email string is the *value* being searched for. A comma-separated list in an env var
does **not** work; it would be compared as one literal string. Use a PHP constant for anything
other than a single address.

### `CISON_CERT_TABLE`

```php
define('CISON_CERT_TABLE', 'wprx_cison_certificates');
```

The value is a **bare table name**, not a prefixed one. `$wpdb->prefix` is not applied by the
controllers that read this constant, so set it to the fully-qualified name including the site's
table prefix.

If the constant is absent:

- `CertController` still works — it hardcodes `'wprx_cison_certificates'` at `CertController.php:14`.
- `DataController.php:15` and `UserController.php:16` resolve to `null` / `''` and build SQL like
  `SELECT * FROM ` or `INNER JOIN  c ON …`, which is a database error. Define the constant.

Note the constant is `define()`d in **four** places with two different derivations
(`Utils/Certificate.php:12` and `CertController.php:14` as literals; `DataController` and
`UserController` via `Config::get()`). PHP emits "Constant already defined" notices and the
first-loaded definition wins, so a value set only in `wp-config.php` is correct but the notices are
noise.

## Secrets

### JWT signing key

```php
// src/Config/Jwt.php
class Jwt {
    const SECRET = 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET';
    const EXPIRY = 3600;   // seconds
    const ISSUER = 'cison';
}
```

`SECRET` is a **class constant, not a `Config::get()` lookup** — there is no way to override it
from `wp-config.php` or the environment. Overriding it means editing `src/Config/Jwt.php`, which
creates a deploy-time diff and a risk of shipping the placeholder to production.

**`CHANGE_THIS_TO_A_LONG_RANDOM_SECRET` is the shipped value.** With it in place, anyone can mint a
token for any allow-listed e-mail address, because the signing key is public in the repository.
Rotate it before deploying, and consider moving it to `Config::get('JWT_SECRET', wp_salt())`.

`EXPIRY` (3600 s) and `ISSUER` (`cison`) are equally hard-coded. `iss` is written into every token
but never validated on decode.

### `SSSECURE_AUTH_KEY`

`CertificationController` computes `hash_hmac('sha256', $cert_key, Config::get('SSSECURE_AUTH_KEY', ''))`
and compares the client's `hmac_key` with `hash_equals()`. WordPress defines `SSSECURE_AUTH_KEY`
in a default `wp-config.php`, so this normally resolves; with an empty key the HMAC is
deterministic and guessable by anyone who knows the request shape. See
[known-issues.md](known-issues.md#certification-hmac-validation).

## Constants defined by the plugin

These are defined by the plugin itself and can be relied on only if the defining file has been
loaded.

| Constant | Value | Defined at |
| --- | --- | --- |
| `MYAPI_PATH` | `plugin_dir_path(__FILE__)` + trailing slash | `main.php:12` |
| `CISON_CURRENT_YEAR` | `(int) date('Y')` | `Money.php:5`, `Certificate.php:8`, `DataController.php:14`, `UserController.php:15` (four times) |
| `CISON_PRIVATE_DIR` | `WP_CONTENT_DIR . '/private/'` | `Utils/Certificate.php:9` |
| `CISON_CERTIFICATE_DIR` | `WP_CONTENT_DIR . '/private/certificates/'` | `Utils/Certificate.php:10` |
| `CISON_CERTIFICATE_URL` | `content_url('/private/certificates/')` | `Utils/Certificate.php:11` |
| `CISON_CERT_TABLE` | `'wprx_cison_certificates'` | `Utils/Certificate.php:12`, `CertController.php:14` |

`CISON_CERTIFICATE_DIR` is used by `CertController::addNewCertification()` to build the PDF path.
Because `Certificate.php` is never required by the autoloader, that reference only resolves if
some other plugin includes the file — see [architecture.md §2](architecture.md#2-autoloading).

## `CISON_CURRENT_YEAR`

Drives certificate ID formatting (`YYYY-NNNNN`) and the year range that
`Money::cison_get_required_fees()` generates fee keys for. It is evaluated at load time, so a
long-running PHP-FPM worker keeps the value it booted with across New Year unless it is restarted.

## `wp-config.php` example

```php
// Authentication allow-list (required — the API denies everyone without it)
define('ACCEPTED_USERS', array(
    'admin@example.com',
    'registrar@example.com',
));

// Certificate table (bare name, table prefix included)
define('CISON_CERT_TABLE', 'wprx_cison_certificates');

// SSSECURE_AUTH_KEY is provided by WordPress itself; nothing to add.

// NOT overridable — edit src/Config/Jwt.php
// const SECRET = 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET';
```

## Runtime dependencies that must already be active

| Dependency | Checked how | If missing |
| --- | --- | --- |
| WooCommerce | `class_exists('WooCommerce')` in `TransactionController.php:80` | `500 woocommerce_not_active` on `/transactions`; other endpoints fatal on `wc_*` calls |
| WooCommerce Subscriptions | `function_exists('wcs_get_users_subscriptions')` in `Money.php` | Silently ignored — recurring dues are not counted as paid |
| BuddyPress / BuddyBoss | `function_exists('bp_get_profile_field_data')` in most places, unguarded in others | Some endpoints return empty arrays; `GET /user_id` and `GET /data/users/education` fatal |
| MySQL `INFORMATION_SCHEMA` | `src/Models/*.php` | Table migrations cannot verify existing columns |

`src/Models/Conference_Model_2025.php` and `src/Models/PreConference_Model_2025.php` require the
MySQL `INFORMATION_SCHEMA` view to test for the presence of the `certid` and `cert_url` columns
before adding them. This works on MySQL and MariaDB, but requires the DB user to have
`SELECT` on `INFORMATION_SCHEMA` (normally granted).

## Deployment checklist

1. Rotate `SRC\Config\Jwt::SECRET` to a high-entropy value (`wp_generate_password(64, false)`).
2. Define `ACCEPTED_USERS` in `wp-config.php`.
3. Define `CISON_CERT_TABLE` with the site's actual table prefix.
4. Ensure `wp-content/private/preconference/` and `wp-content/private/conference/` exist and are
   **not** publicly readable if the generated certificates are confidential — see
   [endpoints/add-2025-preconference.md](endpoints/add-2025-preconference.md#certificate-url).
5. Remove or gate the `error_log()` calls in `main.php` behind `WP_DEBUG`.
6. Restrict `GET /cison/v1/cert/get-2025-preconference` and `/get-2025-conference` — they are
   currently public.
