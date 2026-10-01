# Architecture

How the plugin boots, how classes are loaded, how routes reach the controllers, and what the
plugin does to coexist with BuddyBoss/BuddyPress on the same WordPress install.

## 1. Bootstrap — `main.php`

`main.php` is the single plugin entry point. It is not a class; it is a procedural sequence of
hook registrations, executed once when WordPress includes the plugin file.

```
main.php
├── ABSPATH guard                          (main.php:8)      direct access to the file exits
├── define MYAPI_PATH                      (main.php:12)     plugin_dir_path(__FILE__)
├── plugins_loaded  [priority 0]           (main.php:21)     BuddyBoss bypass filters
│   ├── bb_rest_is_request_to_rest_api     [priority 0]
│   ├── bb_rest_is_allowed                 [priority 999, 2 args]
│   ├── rest_pre_serve_request             [priority 1, 4 args]   remove BuddyBoss CORS
│   └── bp_rest_authentication_errors      [priority 10]
├── init  [cison-api alias check]          (main.php:94)     alternative namespace workaround
├── spl_autoload_register                  (main.php:107)    PSR-4 for the SRC\ namespace
├── SRC\Loader::init()                     (main.php:130)    bootstraps route registration
├── rest_api_init                          (main.php:138)    CORS headers for cison/v1
├── rest_api_init                          (main.php:160)    GET /docs -> swagger.json
├── rest_pre_dispatch  [10, 3 args]        (main.php:175)    request logging
├── rest_authentication_errors [999]       (main.php:194)    global auth override for cison/v1
├── require_once Models/Conference_Model_2025.php   (main.php:215)
├── require_once Models/PreConference_Model_2025.php (main.php:217)
└── init  [priority 1]                     (main.php:219)    create the two 2025 event tables
```

### Why so much BuddyBoss code

The CISON member site runs BuddyBoss Community Builder, which installs aggressive global filters
on the REST API: it forces authentication on every request, treats any `wp-json/*` request as a
platform-internal request, and injects its own CORS headers. Those filters are matched on the
whole request URI, so they also catch `cison/v1`. `main.php` therefore registers a set of
defensive filters whose sole job is to make requests whose URI contains `/wp-json/cison/v1/`
pass through BuddyBoss untouched.

| Hook | Priority | Purpose |
| --- | --- | --- |
| `bb_rest_is_request_to_rest_api` | 0 | Force `true` for our namespace so BuddyBoss classifies the request as a REST request |
| `bb_rest_is_allowed` | 999 | Force `true` — this is the filter that would otherwise require BuddyBoss platform auth |
| `bp_rest_authentication_errors` | 10 | Return `null` (no error) for our namespace |
| `rest_authentication_errors` | 999 | Override `rest_not_logged_in` and `bb_rest_authorization_required` with `true` |
| `rest_pre_serve_request` | 1, then 20 | Remove `bp_rest_allow_all_cors` / `bp_rest_send_cors_headers`, then add our own CORS headers |

**These filters remove BuddyBoss's platform-level checks, they do not remove the plugin's own
`permission_callback`.** Every protected route still runs `SRC\Middleware\Auth::jwt()`; see
[authentication.md](authentication.md).

The `cison-api/v1` namespace check at `main.php:94` is dead weight from an earlier attempt at the
same problem — nothing registers a `cison-api` namespace, so that branch never runs.

## 2. Autoloading

`main.php:107` registers a hand-written PSR-4 autoloader rather than relying on Composer's
`vendor/autoload.php`:

```php
spl_autoload_register(function ($class) {
    if (strpos($class, 'SRC\\') !== 0) {
        return;                                  // not ours, let other autoloaders run
    }
    $relative_class = substr($class, 4);          // strip "SRC\"
    $relative_class = str_replace('\\', '/', $relative_class);
    $file = MYAPI_PATH . 'src/' . $relative_class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
```

`composer.json` declares the identical mapping (`"SRC\\": "src/"`) but `main.php` never requires
`vendor/autoload.php`, so `composer install` is optional — the plugin works standalone.

### Consequence: only classes autoload

The autoloader triggers on **class** references only. `src/Utils/Certificate.php` contains a
**function** library (`cison_get_next_cert_number()`, `cison_preview_user_eligibility()`, …) and
`src/Utils/Money.php` is a class, but nothing ever `require`s `Certificate.php`. The
`use SRC\Utils\Certificate;` statements in `CertController.php:10`, `DataController.php:7` and
`UserController.php:7` are `use` *aliases* — they do not load anything. Functions and constants
from that file are therefore only available if another plugin or mu-plugin includes it.

`src/Utils/database.php` exists and is empty (0 bytes); PSR-4 maps it to the lowercase class
`SRC\Utils\database`, which nothing references.

## 3. Route registration

`SRC\Loader::init()` hooks one callback onto `rest_api_init`:

```php
// src/Loader.php
public static function init()  { add_action('rest_api_init', [self::class, 'routes']); }

public static function routes()
{
    AuthRoute::register();
    HelloRoute::register();
    SubmitRoute::register();
    UserRoute::register();
    ProductRoute::register();
    CertRoute::register();
    DataRoute::register();
    CertificationRoute::register();
    LearnRoute::register();
    ConferenceRegistrationRoute::register();
}
```

Each `*Route::register()` is a pure declaration of `register_rest_route()` calls. Controllers are
static method bags in most cases; `CertificationController` is the one exception — its routes wrap
`$cert = new CertificationController();` in closures because its handlers are instance methods.

### Namespace layout

| Route file | Namespace | Path prefix |
| --- | --- | --- |
| `AuthRoute` | `cison/v1` | `/auth/*` |
| `HelloRoute` | `cison/v1` | `/hello` |
| `SubmitRoute` | `cison/v1` | `/submit` |
| `UserRoute` | `cison/v1` | `/all-users`, `/members`, `/transactions`, … |
| `ProductRoute` | `cison/v1/prod` | `/all-products`, `/bought-product`, `/all-orders` |
| `CertRoute` | `cison/v1/cert` | `/add-2025-preconference`, `/add-new`, … plus `cison/v1/data/get-all` |
| `DataRoute` | `cison/v1/data` | `/users/*` |
| `CertificationRoute` | `cison/v1/certification` | `/create`, `/upload`, `/update`, `/delete` |
| `LearnRoute` | `cison/v1/learn` | `/registration` |
| `ConferenceRegistrationRoute` | `cison/v1` | `/conference-registrations` |

Note that route files assign a namespace to a `global` variable (`global $part_a; $part_a = ...`)
rather than using a local. That works but leaks the variable between route files; `CertRoute` is
the file that depends on it for the `/data/get-all` route it registers out of pattern.

## 4. Request lifecycle

For `POST /wp-json/cison/v1/cert/add-2025-preconference`:

```
1. plugins_loaded  →  main.php installs the BuddyBoss bypass filters
2. init            →  create_*_model_2025() runs dbDelta() for the two 2025 tables
3. rest_api_init   →  Loader::routes() registers every cison/v1 route
                      main.php also registers GET /docs and the CORS filter
4. rest_pre_dispatch [10]  →  main.php logs route, method and query params
5. rest_authentication_errors [999]  →  main.php returns true (no error) for cison/v1
6. permission_callback        →  SRC\Middleware\Auth::jwt($request)
                                  · logged-in WP user (cookie)  → allow
                                  · Authorization: Bearer <jwt> → SRC\Utils\Jwt::decode()
                                  · anything else               → 401 rest_forbidden
7. callback                   →  SRC\Controllers\CertController::add2025PreConference($request)
8. rest_pre_serve_request [1 then 20]  →  BuddyBoss CORS removed, plugin CORS emitted
```

## 5. Data layer

There is no ORM, no repository layer and no query builder. Controllers talk to `$wpdb` directly,
mixing three styles:

1. **Raw SQL with `$wpdb->prepare()`** — `src/Models/*`, `ConferenceRegistrationController`,
   `CertController`, `UserController`
2. **WooCommerce CRUD** — `wc_get_products()`, `wc_get_orders()`, `wc_get_order()`,
   `wc_customer_bought_product()` in `ProductController`, `TransactionController`, `Money`
3. **BuddyPress API** — `bp_get_profile_field_data()`, `bp_get_member_type()`,
   `bp_xprofile_get_groups()` in `UserController`, `DataController`, `LearnController`, `Money`

`Money` is the closest thing to a domain service: it maps a registration year + transiting flag to
a set of required fee keys, resolves which of those fees a member has paid (via WooCommerce orders
and subscriptions), and returns the remainder. See [data-model.md](data-model.md#fee-resolution).

## 6. Logging

`main.php` calls `error_log()` at every stage — plugin load, BuddyBoss filter decisions, route
registration, and per-request details inside `rest_pre_dispatch`. Everything goes to the PHP error
log, including per-request query parameter dumps. On a shared host this will fill logs quickly and
leaks request parameters into the log file. Remove or gate these calls (e.g. behind
`WP_DEBUG`) before production deployment.
