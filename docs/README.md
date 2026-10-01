# CISON REST API — Documentation

Reference documentation for the **CISON `rest_api`** WordPress plugin. The plugin registers a
namespaced WordPress REST API under `cison/v1` and exposes it for the CISON member portal
(membership, dues, certificates, 2025 conference/pre-conference registrations).

- **Base URL (pretty permalinks):** `https://<site>/wp-json/cison/v1`
- **Base URL (plain permalinks):** `https://<site>/?rest_route=/cison/v1`
- **Swagger/OpenAPI document:** [`GET /docs`](api-reference.md#docs)

## Documentation index

| Document | Contents |
| --- | --- |
| [architecture.md](architecture.md) | Plugin bootstrap, PSR-4 autoloading, route registration, BuddyBoss/BuddyPress bypass, request lifecycle |
| [configuration.md](configuration.md) | Every constant, environment variable and external symbol the plugin expects |
| [authentication.md](authentication.md) | JWT issuing/validation, the `Auth::jwt` permission callback, the allow-list |
| [api-reference.md](api-reference.md) | Complete list of every registered route with parameters and response shapes |
| [endpoints/add-2025-preconference.md](endpoints/add-2025-preconference.md) | **Detailed reference for `POST /cert/add-2025-preconference`** — the 2025 pre-conference registration write endpoint |
| [data-model.md](data-model.md) | Custom tables, columns, indexes, and the BuddyPress xprofile field IDs used |
| [known-issues.md](known-issues.md) | Known bugs, undefined symbols and security caveats found while documenting |

## Source layout

```
rest_api/
├── main.php                     WordPress plugin bootstrap (BuddyBoss bypass, autoloader, CORS, /docs)
├── composer.json                PSR-4 map: SRC\  ->  src/
├── swagger.json                 OpenAPI 3.0 spec served at GET /wp-json/cison/v1/docs
├── users.json                   Data dump of member records (id, username, display_name, email, member_id)
└── src/
    ├── Loader.php               Registers every *Route class on `rest_api_init`
    ├── Config/
    │   ├── Config.php           Config::get() — constant, then getenv(), then default
    │   └── Jwt.php              JWT_SECRET / EXPIRY / ISSUER constants
    ├── Controllers/             Request handling + business logic
    ├── Routes/                  register_rest_route() declarations only
    ├── Models/                  dbDelta() table creation for the 2025 event tables
    ├── Middleware/
    │   ├── Auth.php             Auth::jwt() permission callback
    │   └── RateLimit.php        RateLimit::limit() — defined but not wired to any route
    ├── Utils/
    │   ├── Jwt.php              HS256 encode/decode
    │   ├── Response.php         Response::success() / Response::error() envelope
    │   ├── Money.php            Required/paid/unpaid fee resolution, product-ID maps
    │   ├── Certificate.php      Certificate constants + eligibility helpers (function library)
    │   └── database.php         Empty placeholder
    └── docs/Membership.md       Working notes on unpaid-fee resolution (not API docs)
```

## Conventions used across the API

The codebase predates a strict response-envelope convention, so responses fall into three shapes.
Know which one to expect before writing a client:

1. **`Response::success()` envelope** — `{ "status": "success", "data": { ... } }`
   (used by `AuthController`, `SubmitController`, `HelloController`, most of `UserController`)
2. **Ad-hoc envelope** — a bespoke combination of `status` / `data` / `count` / `filters`
   (used by `DataController`, `TransactionController`, `ConferenceRegistrationController`)
3. **Bare array** — a top-level JSON array of rows with no envelope at all
   (used by `ProductController`, `CertController::get2025Preconference`, `DataController::allUsers`)

Errors are always `WP_Error` objects, which WordPress serialises as:

```json
{ "code": "invalid_data", "message": "Email is required", "data": { "status": 400 } }
```

## Runtime dependencies

This plugin is not self-contained; it assumes the following are already active on the site:

| Dependency | Used for | Graceful degradation |
| --- | --- | --- |
| WordPress 5.0+ (with permalinks) | Everything | None — hard requirement |
| PHP 7.2+ | Everything (uses `str_starts_with`, so effectively PHP 8+ in practice) | None — hard requirement |
| WooCommerce | Products, orders, transaction endpoints, fee/dues resolution | Partially — some endpoints return 500 |
| WooCommerce Subscriptions | Recurring dues detection in `Money::cison_get_paid_fees()` | Yes — guarded with `function_exists()` |
| BuddyPress / BuddyBoss | Member profiles, xprofile fields, groups, member types | Partially — some endpoints return empty arrays, some fatal |
| `CISON_CERT_TABLE`, `ACCEPTED_USERS`, `SSSECURE_AUTH_KEY` constants/env vars | Auth allow-list, certificate table name, HMAC keys | No — see [configuration.md](configuration.md) |

See [known-issues.md](known-issues.md) for the symbols that are referenced but never defined in
this repository.
