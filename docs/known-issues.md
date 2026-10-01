# Known Issues

Defects found while documenting this codebase. Each entry gives the location, the observed
behaviour, and the impact. Nothing here is speculative unless marked as such — most were read
directly off the source.

Grouped by severity. **Critical** and **High** items affect security or data integrity.

---

## Critical

### JWT secret is a placeholder in version control

`src/Config/Jwt.php:7`

```php
const SECRET = 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET';
```

It is a class constant, so it cannot be overridden by `wp-config.php` or the environment — it must
be edited in source. With the shipped value in place, anyone who can read the repository can mint a
token for any address on the `ACCEPTED_USERS` allow-list and call every protected endpoint,
including the 2025 registration writes and the table-drop route.

**Fix:** generate a real secret, and move the lookup to `Config::get('JWT_SECRET', wp_salt())`.

### Registrant PII is served unauthenticated

`src/Routes/CertRoute.php:46-56` — `get-2025-preconference` and `get-2025-conference` both use
`'permission_callback' => '__return_true'`.

They return `id, first_name, last_name, email, cert_url` for **every** registrant, unpaginated, to
anonymous callers. `cert_url` is a direct link to the certificate PDF. If registrant e-mail
addresses are not public information, these two routes leak them.

**Fix:** change both to `[Auth::class, 'jwt']`.

### `GET /cert/drop-conference-tables` destroys registration data

`src/Routes/CertRoute.php:58-62` → `CertController::dropTables()` (`CertController.php:379`)

Executes `DROP TABLE IF EXISTS` against `{prefix}cison_preconference_2025` **and**
`{prefix}cison_conference_2025`, deleting every conference and pre-conference registration on the
site. It is:

- reachable by `GET`, so any prefetch, link preview or crawler that carries the token triggers it;
- protected only by `Auth::jwt`, which passes for any allow-listed address;
- undocumented, and its `WHERE`-less nature means no confirmation is possible.

**Fix:** delete the route. It is a development utility that was never removed.

### `POST /upgrade-statistician/` is unauthenticated

`src/Routes/UserRoute.php:86-98`

The route array is built positionally instead of by key, so `permission_callback` is never set:

```php
register_rest_route('cison/v1', '/upgrade-statistician/', array(
    'methods' => 'POST',
    'callback' => [UserController::class, 'handle_statistician_upgrade_endpoint'],
    [Auth::class, 'jwt'],              // ← array element 0, not 'permission_callback' => …
    'args' => array( /* … */ ),
));
```

`[Auth::class, 'jwt']` becomes a numeric-keyed element and the `=>` before it is missing, so the
authorisation callback is silently dropped. WordPress **skips the permission check when the key is
absent** — `WP_REST_Server::dispatch()` guards it with
`! empty( $handler['permission_callback'] )` and simply runs the callback if it is empty.

The handler (`UserController.php:584`) converts an arbitrary user to the BuddyBoss member type
`registered-statistician` and writes `bp_profile_type` user meta. Its only guard is
`$user_id === 1`. So any anonymous caller can promote any other account:

```bash
curl -X POST 'https://example.com/wp-json/cison/v1/upgrade-statistician/' \
  -H 'Content-Type: application/json' -d '{"user_id":42}'
```

**Fix:** restore the key — `'permission_callback' => [Auth::class, 'jwt'],` — and give the handler a
real capability check rather than relying on the route alone. The `'args'` map on this route is
correctly keyed, so it does apply.

### ~~Unvalidated, unsanitised registration payloads are persisted~~ — fixed for pre-conference

**Fixed for `POST /cert/add-2025-preconference`.** `POST /cert/add-2025-conference` still has every
part of this issue. See
[endpoints/add-2025-preconference.md](endpoints/add-2025-preconference.md).

Both `POST /cert/add-2025-preconference` and `POST /cert/add-2025-conference` now declare the same
name-keyed `args` map (`CertController::get2025RegistrationArgs()`), and both handlers call
`CertController::getRequiredNames()`, so neither can accept a nameless registrant. Every body field
is guarded and cast, text fields go through `sanitize_text_field()`, the e-mail is sanitised once
and that same value is used for both the duplicate lookup and the insert, and `cert_name` is
validated as a bare file name and passed through `sanitize_file_name()`. A `"../../…"` or `"dir/…"`
value is rejected with `400` instead of being concatenated into `cert_url`.

Sharing one schema between the two routes is deliberate: they previously drifted, which is how the
conference endpoint ended up with no schema and no name requirement at all.

---

## High

### `submit` endpoint fatal

`src/Routes/SubmitRoute.php:15`

```php
'permission_callback' => [Auth::class, 'api_key'],
```

`SRC\Middleware\Auth` defines only `jwt()`. Calling `POST /cison/v1/submit` raises
`Call to undefined method SRC\Middleware\Auth::api_key()`. The route can never succeed.

### Five `GET` routes read the JSON body instead of the query string

`/user`, `/user_id`, `/data/users/education`, `/learn/registration` and `/prod/bought-product` are
registered with `'methods' => 'GET'` but their handlers call `$request->get_json_params()`.

`GET` with a body is not reliably supported — `curl` needs `-X GET --data`, many HTTP libraries
drop the body on GET, and some proxies strip it. Query parameters are ignored entirely, so
`?user_id=1` returns `400 User ID or Member ID is required`. Either re-register these as `POST`, or
switch the handlers to `$request->get_param()`.

(`/cert/single-certificate` and `/cert/remove-cert` are unaffected — they use
`$request->get_params()`, which does read the query string on a `GET`.)

### `has_certificate` is always empty

`src/Controllers/UserController.php` — three methods compare an `ARRAY_A` value to a native `int`
with `===`:

```php
$user_id = (int) $all_users[$i]['ID'];              // int
if ($certificate["user_id"] === $user_id) {         // string vs int → never equal
```

`wpdb` returns strings in `ARRAY_A` mode, so `===` is always false. `has_certificate` is therefore
always empty in `getMemebersThatAreTransitingThatHavePaid` (`:106`), `getMembersThatDoNotHaveCertificate`
(`:225`) and `getUserWithUserId` (`:282`). Use `==` or cast both sides.

### Undefined variables in `hascertificate`

`UserController.php:204-205` use `$has_certificate` and `$certificate_validity` inside the response
array; neither is ever assigned in the loop. Both fields emit PHP warnings and serialise as `null`
for every row. The method also hardcodes `wprx_cison_certificates` instead of `CISON_CERT_TABLE`,
and queries `$all_users` without using the result.

### Global fee functions are never defined

`DataController` and `UserController` call `cison_get_required_fees()`,
`cison_get_paid_fees()` and `cison_get_unpaid_fees()` as **unqualified global functions**, with
3–4 arguments. The only definitions in this repository are 2-argument static methods on
`SRC\Utils\Money`. Unless a companion plugin defines the globals, these calls are fatal:

| Call site | Arity |
| --- | --- |
| `DataController.php:245`, `:292`, `:333` | 4 |
| `CertController.php:477` | 3 |
| `UserController.php:252`, `:314` | global `cison_get_paid_fees($user_id)` |
| `Utils/Certificate.php:62-64` | global fee functions |

`UserController.php:143` correctly uses `Money::cison_get_paid_fees()`. These are the only three
endpoints that work: **`/data/users/{complete,partial,no}-payment-latest` fatal.**

### `Utils/Certificate.php` is never loaded

The PSR-4 autoloader in `main.php` triggers on class names only. `src/Utils/Certificate.php`
contains a **function** library, and nothing `require`s it — the `use SRC\Utils\Certificate;`
statements in `CertController.php:10`, `DataController.php:7` and `UserController.php:7` are
aliases and load nothing.

Consequently `cison_get_next_cert_number()` (used by `GET /cert/next-number`), the
`CISON_CERTIFICATE_DIR` constant (used by `POST /cert/add-new`), `cison_preview_user_eligibility()`
and `cison_check_eligibility_and_create_row_if_missing()` are unavailable unless something else
includes the file. `src/Utils/database.php` is an empty 0-byte placeholder.

### Certificate ID sequence has two conflicting implementations

| Implementation | Method | Scope |
| --- | --- | --- |
| `cison_get_next_cert_number()` (`Utils/Certificate.php:16`) | `COUNT(*) + 1` | **all years** |
| `CertController::addNewCertification()` (`CertController.php:111`) | `MAX(CAST(SUBSTRING_INDEX(cert_id,'-',-1))) + 1` under a MySQL advisory lock | current year only |

`GET /cert/next-number` reports the first, while `POST /cert/add-new` allocates the second. The
values disagree, and `COUNT(*) + 1` collides whenever a `cert_id` has been deleted. Use the
second scheme everywhere.

### ~~`add2025PreConference` truncates monetary amounts~~ — fixed

**Fixed for `POST /cert/add-2025-preconference`.** `add2025Conference` still writes a Unix integer
into the `last_updated` timestamp column, which is invalid for that type and fails under MySQL
strict mode, and its format array has 15 entries for 17 columns. See
[endpoints/add-2025-preconference.md](endpoints/add-2025-preconference.md#money-uses-f).

`item_price` and `order_total` are now cast to `float` and formatted with `%f`, so `150.50` stores
as `150.50`. The `last_updated` column is no longer written at all — it was receiving a Unix epoch
integer — so `DEFAULT CURRENT_TIMESTAMP` applies. The `$data` and `$formats` arrays are now the same
length, also correcting a mismatch that left the last column unformatted.

Note the sibling `add2025Conference` was never affected by the truncation: it uses `%s` for its
money columns, so MySQL coerced them into `decimal(10,2)` without losing cents.

---

## Medium

### Certification HMAC validation

`src/Controllers/CertificationController.php:158`

```php
$cert_hmac = hash_hmac('sha256', $cert_key, Config::get('SSSECURE_AUTH_KEY', ''));
```

`hash_equals()` is called without first checking that either operand is a non-empty string, and
`SSSECURE_AUTH_KEY` falls back to `''`, which makes the HMAC deterministic and guessable. Related
defects in the same handler:

- `$user = $wpdb->get_row(...); $user_id = (int) $user->ID;` (`:154`) has **no null check** — an
  unknown e-mail inserts a row with `user_id = 0` after a "property on null" warning.
- `$file_url` is assigned `$upload_result['file_path']` — an **absolute filesystem path** — and the
  real URL is discarded (`:202`).
- `WP_Error`s at `:123`, `:143`, `:166`, `:177` omit `['status' => …]`, so validation failures
  serialise as HTTP 500.
- `$wpdb->show_errors()` is force-enabled (`:117`), printing raw SQL errors into the response.
- `handle_upload_certificate` uses raw `move_uploaded_file()` with no MIME or extension
  allow-list, and returns the absolute server path.
- `handle_delete_certificate` removes the row but orphans the file and the attachment.
- `/create` and `/update` store files in the **publicly served** `wp-content/uploads/<cert name>/`,
  while `/upload` uses `wp-content/private/certificates/`.

### Argument schemas passed as callables are silently ignored

WordPress only applies an `args` **map keyed by parameter name**. `WP_REST_Request::has_valid_params()`
iterates it as `foreach ( $this->get_attributes()['args'] as $key => $arg )`, so a two-element
`[ClassName, 'method']` value is never used as a callable — the loop body does not run, no
validation happens, and no error is raised. It looks like validation but does nothing.

Two routes use that form:

| Route | Code | Effect |
| --- | --- | --- |
| `/transactions` | `UserRoute.php:79` — `[TransactionController::class, "get_endpoint_args"]` | No date validation, no `absint`, no `per_page` bounds. A malformed date silently changes the query rather than returning `400`. |
| `/register-conference` | `ConferenceRegistrationRoute.php:16` — `[ConferenceRegistrationController::class, 'get_endpoint_args']` | No validation of the registration payload. |

Fix by calling the method at registration time, as
`/cert/add-2025-preconference` now does:

```php
'args' => TransactionController::get_endpoint_args(),
```

Related in `TransactionController`: `validate_date()` is declared non-static but referenced
statically, which would fatal once the schema is wired; and `X-WP-TotalPages` is passed a float.

### Query parameters ignored on payment-status endpoints

`/data/users/education` (`DataController.php:353`) reads `$request->get_json_params()` on a `GET`
route, so `?user_id=1` is ignored and the request 400s.

The response also returns `group` as raw `BP_XProfile_Group` objects, and the group loop `break`s
on the first `education` match so only one group is ever returned.

### `getAllProducts` returns unpublished products

`ProductController::getAllProducts` requests `status => ['publish', 'draft', 'pending', 'private']`
with `limit => -1`. Draft and private WooCommerce products — including pricing for
not-yet-public events — are exposed to any authenticated token holder.

### Unpaginated full-table endpoints

`GET /prod/all-products`, `/prod/all-orders`, `/cert/list-cert`, `/cert/get-2025-preconference`,
`/cert/get-2025-conference`, `/members`, `/transiting` and `/cert/get-qualified-candidate` all
return entire tables. `/transiting` in particular returns every `wp_bp_xprofile_data` row —
a full profile dump, unfiltered despite the name — and `/members` returns every group-membership
row. On a site of CISON's size these will time out and will not paginate as the data grows.

`DataController::allUsers` (`GET /data/get-all`) is worse: ~190 BuddyPress queries per user across
every row of `wp_users`, with field IDs as JSON keys and no `user_id` in the output.

### Fee cache has no invalidation

`Money::cison_get_paid_fees()` caches in transient `cison_paid_fees_{user_id}` for 15 minutes, and
nothing clears it on order completion. Payment status is up to 15 minutes stale. The key is not
namespaced by registration year, so it is also shared across the `_till_2025` variant's separate key
set.

### Required-fee entries with empty product IDs

`Money::cison_get_required_fees()`, non-transiting branch (`Money.php:417-426`), creates
`annual_dues_{Y}` / `dev_levy_{Y}` keys even when the year has no product-ID mapping, producing
`product_ids => []`. Those entries can never be satisfied, so members with a registration year
outside the mapped range appear permanently non-compliant.

Related: `cison_get_paid_fees()` matches **any** completed or processing order with no date window,
so a 2024 dues payment on an old order keeps `annual_dues_2024` satisfied indefinitely.

### `Money::getArrayCount` conflates "partial" outcomes

Returns `0` for any mixed map. One unpaid fee and five unpaid fees are both reported as
"partial payment" by `/data/users/partial-payment` and the `-latest` variants. The full `unpaid`
map is in the response, so clients can disambiguate — but the bucket label cannot.

### `POST /cert/add-new` writes a row but no PDF

`CertController::addNewCertification()` derives the path
`CISON_CERTIFICATE_DIR . "certificate_{$cert_id}.pdf"` and inserts it, but never generates the file.
Its own re-entrancy guard (`file_exists($existing_cert->certificate_path)`) therefore never
trips, and `certificate_path` points at nothing. The `wpdb` format array for the insert is also
mis-ordered relative to the data keys.

The related `Utils/Certificate.php` function is explicit about this: *"this only creates the DB
row — it does not generate the PDF."*

### `cison_create_row_for_certification()` is broken

`src/Utils/Certificate.php:237` references `$preview['applied_cutoff']` (`:253`) and
`$existing->cert_id` (`:269`); neither variable is defined in the function. It performs no
eligibility check and will insert duplicates.

### Registration-year bounds are hardcoded

`CertController::get_qualified_certificates` (`:475`) uses
`max(2024, min((int) substr($member_id, 0, 4), 2025))` — literal years that do not track
`CISON_CURRENT_YEAR`. The same bounds appear throughout `Money`'s product maps.

---

## Low

### Activation hooks never fire

`src/Models/Conference_Model_2025.php:76` and `src/Models/PreConference_Model_2025.php:78` call
`register_activation_hook(__FILE__, …)`, but `__FILE__` is the model file — WordPress only honours
activation hooks registered from the main plugin file. The `init` call in `main.php:219` is what
actually creates the tables.

### `dbDelta()` runs on every request

Both `create_*_model_2025()` functions execute on `init` at priority 1 — every page load, every REST
request. `dbDelta()` issues `DESCRIBE` round-trips and the two `INFORMATION_SCHEMA` queries add
more. Gate this behind a version option.

### Constant defined four times

`CISON_CURRENT_YEAR` is `define()`d in `Money.php:5`, `Certificate.php:8`, `DataController.php:14`
and `UserController.php:15`; `CISON_CERT_TABLE` in four places with two different derivations.
PHP emits "Constant already defined" notices and the **first-loaded** definition wins, so results
are load-order dependent. Consolidate into one config file.

`CISON_CERT_TABLE` derived via `Config::get('CISON_CERT_TABLE')` with **no default**
(`DataController.php:15`) resolves to `null` when unset, producing SQL like `SELECT * FROM ` —
a database error rather than a clear failure.

### `swagger.json` is a stub and documents a route that does not exist

It declares only `/auth/login`. The real token route is `/auth/api-key`. It is served verbatim as
`text/plain` at `GET /cison/v1/docs`, with no `Content-Type: application/json`.

### `Auth::api_key` and `RateLimit` are unwired

`SubmitRoute` calls a method that does not exist (see above). `RateLimit::limit()` is never
registered on any route, and returns a `WP_Error` rather than a boolean — not the contract a
`permission_callback` expects.

### Miscellaneous

- `AuthController::create()` echoes the submitted `$email` and `$name` back into its 400 message
  (`AuthController.php:67`).
- `LearController` returns a bare `WP_Error("MemberID is not Valid")` with no status array, so it
  serialises as HTTP 500 for a validation failure.
- `ProductController.php:41` — `if (!$user_id | $product_id === 0)` is a **bitwise** OR, so only a
  falsy `user_id` triggers the 404; a missing `product_id` falls through and queries product `0`.
- `UserController.php:330` — `$prod->get_name` is missing `()`, emitting a notice and the literal
  `"WC_Product::__get"` instead of the product name.
- `UserController.php:6` — `use SRC\Utils\money;` (lowercase) against the class `Money`. Works only
  because PHP class names are case-insensitive. `Certificate` is imported but unused.
- `UserController.php:545`, `:584` — two methods are `static` without `public`.
- `TransactionController.php:170` passes a float to `X-WP-TotalPages`.
- `CertController.php:237-251` — the duplicate-email check in `add2025Conference` is written twice;
  the second is unreachable.
- `CertController.php:257`, `:261` — `$cert_id` and `$cert_url` are computed and never stored.
- `CertController.php:288` — the conference endpoint's failure message says
  `'Failed to save pre-conference record'`.
- `main.php:94-103` — the `cison-api/v1` namespace workaround is dead: nothing registers that
  namespace.
- `main.php:175-188` — `rest_pre_dispatch` logs every query parameter for every `cison/v1` request
  to the PHP error log. Remove or gate behind `WP_DEBUG`.
- `DataController.php:353` — `getEducationForUserID` calls `bp_xprofile_get_groups()` unguarded,
  as does `UserController::getUserIDWithMemberId`; both fatal without BuddyPress.
- `src/docs/Membership.md` is working notes containing an unbalanced `if` block
  (`if (empty($paid_fees[$key]){`) and refers to `is_product_in_required_fees`, which is not
  defined anywhere in the repository.

---

## Symbols referenced but never defined

Anything here will fatal unless a companion plugin or mu-plugin provides it.

| Symbol | Kind | Referenced at |
| --- | --- | --- |
| `cison_get_required_fees()` | global function (3–4 args) | `DataController.php:245`, `:292`, `:333`; `CertController.php:477`; `Utils/Certificate.php:62` |
| `cison_get_paid_fees()` | global function | `UserController.php:252`, `:314`; `Utils/Certificate.php:63` |
| `cison_get_unpaid_fees()` | global function | `Utils/Certificate.php:64` |
| `cison_get_last_payment_date()` | global function | `Utils/Certificate.php:86` |
| `cison_get_cutoffs_option()` | global function | `Utils/Certificate.php:89` |
| `is_product_in_required_fees()` | global function | `src/docs/Membership.md:57` (notes only) |
| `SRC\Middleware\Auth::api_key()` | class method | `SubmitRoute.php:15` |
| `ACCEPTED_USERS` | constant / env var | `AuthController.php:22`, `:74`; `Utils/Jwt.php:54` |
| `SSSECURE_AUTH_KEY` | constant / env var | `CertificationController.php:158` (WordPress normally defines it) |
| `CISON_CERT_TABLE` | constant / env var | `DataController.php:15`; `UserController.php:16` |

Also note that `use SRC\Models\CISON_PreConference_Model_2025;` and
`use SRC\Models\CISON_Conference_Model_2025;` (`CertController.php:9-10`) name classes that do not
exist — the model files define **functions** in `SRC\Models`, not classes.
