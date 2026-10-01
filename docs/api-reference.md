# API Reference

All paths are relative to the namespace root. With pretty permalinks the full URL is
`https://<site>/wp-json/cison/v1/<path>`; without them it is `https://<site>/?rest_route=/cison/v1/<path>`.

**Auth column legend**

| Mark | Meaning |
| --- | --- |
| 🔓 | Public — `permission_callback` is `__return_true` |
| 🔒 | JWT or logged-in WordPress session — `[Auth::class, 'jwt']` |
| ⚠️ | Registered but broken — see [known-issues.md](known-issues.md) |

## Endpoint index

| Method | Path | Auth | Handler |
| --- | --- | --- | --- |
| GET | [/docs](#docs) | 🔓 | inline in `main.php:164` |
| GET | [/hello](#hello) | 🔓 | `HelloController::handle` |
| POST | [/auth/api-key](#authapi-key) | 🔓 | `AuthController::login` |
| POST | [/auth/register](#authregister) | 🔓 | `AuthController::create` |
| POST | [/submit](#submit) | ⚠️ | `SubmitController::handle` — `Auth::api_key` missing |
| GET | [/all-users](#all-users) | 🔒 | `UserController::getAllUsers` |
| GET | [/members](#members) | 🔒 | `UserController::getGroupMembers` |
| GET | [/transiting](#transiting) | 🔒 | `UserController::getTransitingMembers` |
| GET | [/validtransiting](#validtransiting) | 🔒 | `UserController::getMemebersThatAreTransitingThatHavePaid` |
| GET | [/hascertificate](#hascertificate) | 🔒 | `UserController::getMembersThatHaveCertificate` |
| GET | [/nocertificate](#nocertificate) | 🔒 | `UserController::getMembersThatDoNotHaveCertificate` |
| GET | [/certificate](#certificate) | 🔒 | `UserController::allCertificate` |
| GET | [/user](#user) | 🔒 | `UserController::getUserWithUserId` |
| GET | [/user_id](#user_id) | 🔒 | `UserController::getUserIDWithMemberId` |
| GET | [/validusers](#validusers) | 🔒 | `UserController::getValidUsers` |
| GET | [/invalidusers](#invalidusers) | 🔒 | `UserController::getInvalidUsers` |
| GET | [/without-profile-type](#without-profile-type) | 🔒 | `UserController::get_registered_unsigned_users` |
| POST | [/upgrade-statistician/](#upgrade-statistician) | 🔓 | `UserController::handle_statistician_upgrade_endpoint` |
| GET | [/transactions](#transactions) | 🔒 | `TransactionController::get_transactions` |
| GET | [/conference-registrations](#conference-registrations) | 🔒 | `ConferenceRegistrationController::get_transactions` |
| GET | [/prod/all-products](#prodall-products) | 🔒 | `ProductController::getAllProducts` |
| GET | [/prod/bought-product](#prodbought-product) | 🔒 | `ProductController::checkWhetherProductWasPurchasedByUser` |
| GET | [/prod/all-orders](#prodall-orders) | 🔒 | `ProductController::getOrders` |
| GET | [/cert/next-number](#certnext-number) | 🔒 | `CertController::getNextCertNumber` |
| POST | [/cert/add-new](#certadd-new) | 🔒 | `CertController::addNewCertification` |
| GET | [/cert/single-certificate](#certsingle-certificate) | 🔒 | `CertController::singleCertificate` |
| DELETE | [/cert/remove-cert](#certremove-cert) | 🔒 | `CertController::remove_certificate` |
| GET | [/cert/list-cert](#certlist-cert) | 🔒 | `CertController::list_certificates` |
| GET | [/cert/get-qualified-candidate](#certget-qualified-candidate) | 🔒 | `CertController::get_qualified_certificates` |
| POST | [/cert/add-2025-conference](#certadd-2025-conference) | 🔒 | `CertController::add2025Conference` |
| **POST** | **[/cert/add-2025-preconference](#certadd-2025-preconference)** | 🔒 | **`CertController::add2025PreConference`** |
| GET | [/cert/get-2025-preconference](#certget-2025-preconference) | 🔓 | `CertController::get2025Preconference` |
| GET | [/cert/get-2025-conference](#certget-2025-conference) | 🔓 | `CertController::get2025Conference` |
| GET | [/cert/drop-conference-tables](#certdrop-conference-tables) | 🔒 | `CertController::dropTables` |
| GET | [/data/get-all](#dataget-all) | 🔒 | `DataController::allUsers` |
| GET | [/data/users/complete-payment](#datauserscomplete-payment) | 🔒 | `DataController::users_with_cleared_payments_2025_limit` |
| GET | [/data/users/partial-payment](#datauserspartial-payment) | 🔒 | `DataController::users_with_partial_payments_2025_limit` |
| GET | [/data/users/no-payment](#datausersno-payment) | 🔒 | `DataController::users_without_payments_2025_limit` |
| GET | [/data/users/complete-payment-latest](#datauserscomplete-payment-latest) | 🔒 | `DataController::users_with_cleared_payments` |
| GET | [/data/users/partial-payment-latest](#datauserspartial-payment-latest) | 🔒 | `DataController::users_with_partial_payments` |
| GET | [/data/users/no-payment-latest](#datausersno-payment-latest) | 🔒 | `DataController::users_without_payments` |
| GET | [/data/users/education](#datauserseducation) | 🔒 | `DataController::getEducationForUserID` |
| GET | [/learn/registration](#learnregistration) | 🔒 | `LearnController::get_user_details_from_memberid` |
| POST | [/certification/create](#certificationcreate) | 🔒 | `CertificationController::handle_create_certificate` |
| POST | [/certification/upload](#certificationupload) | 🔒 | `CertificationController::handle_upload_certificate` |
| PUT | [/certification/update](#certificationupdate) | 🔒 | `CertificationController::handle_update_certificate` |
| DELETE | [/certification/delete](#certificationdelete) | 🔒 | `CertificationController::handle_delete_certificate` |

> ⚠️ `/cert/drop-conference-tables` **drops both 2025 event tables**. It is JWT-protected but
> exposes a destructive administrative action to any allow-listed token holder. Consider removing
> it from production.

---

## docs

**`GET /docs`** — `main.php:162`

Returns the contents of `swagger.json` verbatim as `text/plain`. The bundled `swagger.json` is
OpenAPI 3.0.0 but only documents `/auth/login`, which **does not exist** (the real route is
`/auth/api-key`). Treat this as a stub; the tables in this document are the real reference.

## hello

**`GET /hello`** — `HelloController::handle`

```json
{ "status": "success", "data": { "message": "Hello from modular WordPress API" } }
```

Useful as a smoke test that the plugin is active and the namespace is registered.

## auth/api-key

See [authentication.md § Obtaining a token](authentication.md#obtaining-a-token). Requires
`{ "email": "..." }`; returns `{ "status": "success", "data": { "token": "<jwt>" } }`.

## auth/register

See [authentication.md](authentication.md#obtaining-a-token). Requires `{ "email", "name" }`;
inserts a bare `wp_users` row for an allow-listed address.

## submit

**`POST /submit`** — ⚠️ **fatal.** `SubmitRoute.php:15` registers
`'permission_callback' => [Auth::class, 'api_key']`, but `SRC\Middleware\Auth` only defines `jwt()`.
Calling this route raises `Call to undefined method SRC\Middleware\Auth::api_key()`.

Intended body (from `SubmitController::handle`): `name`, `email` read via `get_param()`, returning
`{ "status": "success", "data": { "name": "...", "email": "..." } }`.

## all-users

**`GET /all-users`** — `UserController::getAllUsers`

No parameters. Returns a **bare JSON array** (no envelope) of every row in `wp_users`:

```json
[
  {
    "user_id": 2695,
    "first_name": "Ada",
    "middle_name": "",
    "last_name": "Lovelace",
    "user_login": "ada.lovelace@example.com",
    "user_email": "ada.lovelace@example.com",
    "joined_date": "2025-01-14 09:12:33",
    "phone_number": "",
    "display_name": "Ada Lovelace",
    "member_id": "20260261"
  }
]
```

Field IDs come from BuddyPress xprofile (1 = first name, 864 = middle name, 2 = last name,
5 = phone, 894 = member ID). ~5 xprofile lookups per user — O(n) queries.

## members

**`GET /members`** — `UserController::getGroupMembers`

No parameters. Returns a **bare array of raw `wp_bp_groups_members` rows** — no filtering, no
projection. Every group-membership record on the site.

## transiting

**`GET /transiting`** — `UserController::getTransitingMembers`

No parameters. Returns a **bare array of raw `wp_bp_xprofile_data` rows** — despite the name, no
filtering is applied, so this is every xprofile value for every user. Effectively a full profile
dump. **Do not expose to non-admin consumers.**

## validtransiting

**`GET /validtransiting`** — `UserController::getMemebersThatAreTransitingThatHavePaid`

No parameters. Returns members whose xprofile field 1595 (`Is Transiting`) equals `Yes`:

```json
{
  "data": [
    {
      "user_id": 2695, "first_name": "...", "middle_name": "...", "last_name": "...",
      "user_login": "...", "user_email": "...", "joined_date": "...", "phone_number": "...",
      "display_name": "...", "member_id": "20260261",
      "certificate_validity": null, "has_certificate": null,
      "paid_fees": { "annual_dues_2025": true },
      "is_transiting": true
    }
  ],
  "status": "success"
}
```

`has_certificate` and `certificate_validity` are always `null`/empty due to a strict-comparison
bug — see [known-issues.md](known-issues.md#has_certificate-is-always-empty).

## hascertificate

**`GET /hascertificate`** — `UserController::getMembersThatHaveCertificate`

No parameters. Intended to list members holding a certificate, returning
`{ "data": [{ user_id, first_name, middle_name, last_name, has_certificate, certificate_validity }], "status": "success" }`.

⚠️ `$has_certificate` and `$certificate_validity` are never assigned in the loop — they are
undefined variables, so both fields come back `null` for every row.

## nocertificate

**`GET /nocertificate`** — `UserController::getMembersThatDoNotHaveCertificate`

No parameters. Returns members **without** a certificate row:

```json
{ "data": [ { "user_id": 1, "first_name": "...", "middle_name": "...", "last_name": "...",
              "has_certificate": null, "certificate_validity": null,
              "paid_fees": {}, "is_transiting": false } ], "status": "success" }
```

## certificate

**`GET /certificate`** — `UserController::allCertificate`

No parameters. `{ "data": [ ...all rows of the certificate table... ], "status": "success" }`.
Hardcodes the table name `wprx_cison_certificates` rather than using `CISON_CERT_TABLE`. Row
shape follows the table definition in [data-model.md](data-model.md#cison_cert_table).

## user

**`GET /user`** — `UserController::getUserWithUserId`

⚠️ Registered as `GET` but reads the **JSON body**, not query parameters. A GET with a body is
rejected by many HTTP clients and by some proxies; `?user_id=1` in the query string is ignored and
the request returns `400 User ID or Member ID is required`.

```http
GET /wp-json/cison/v1/user HTTP/1.1
Content-Type: application/json

{ "user_id": 2695 }
```

Response:

```json
{
  "data": {
    "user_id": 2695, "first_name": "...", "middle_name": "...", "last_name": "...",
    "has_certificate": null, "certificate_validity": null,
    "Joined": "2025-01-14 09:12:33",
    "paid_fees": { "annual_dues_2025": true },
    "member_id": "20260261", "is_transiting": true,
    "orders": [ { "product_id": 5035, "product_name": "2025 Annual Dues" } ]
  },
  "status": "success"
}
```

## user_id

**`GET /user_id`** — `UserController::getUserIDWithMemberId`

⚠️ Same body-vs-GET problem. Body `{ "member_id": "20260261" }` → `{ "user_id": 2695, "status": "success" }`.
Resolves through `wp_bp_xprofile_data` where `field_id = 894`. `400` if `member_id` is missing,
`404 not_found` if no user matches. Calls `bp_get_profile_field_data()` unguarded — fatal without
BuddyPress.

## validusers

**`GET /validusers`** — `UserController::getValidUsers`

No parameters. `INNER JOIN` between `wp_users` and the certificate table, returning members who
**have** a certificate row:

```json
{ "data": [ { "user_id": 2695, "first_name": "...", "middle_name": "...", "last_name": "...",
              "user_login": "...", "user_email": "...", "joined_date": "...",
              "phone_number": "...", "display_name": "...", "member_id": "20260261",
              "is_transiting": true } ],
  "status": "success", "count": 12 }
```

## invalidusers

**`GET /invalidusers`** — `UserController::getInvalidUsers`

Same shape and semantics as `validusers`, `LEFT JOIN ... WHERE c.user_id IS NULL` — members with
**no** certificate row. `count` is present on this response too.

Both `validusers` and `invalidusers` return `500 db_error` if the `CISON_CERT_TABLE` constant is
missing, and both return an empty list if BuddyPress is inactive.

## without-profile-type

**`GET /without-profile-type`** — `UserController::get_registered_unsigned_users`

No parameters. Members registered on the site who have **no BuddyPress member type** assigned.

- None found → `{ "message": "All users have a profile type assigned." }`
- Otherwise a **bare array**: `[ { "id": 2721, "username": "grace", "display_name": "Grace",
  "email": "grace.hopper@example.com", "member_id": "" } ]`

## upgrade-statistician

**`POST /upgrade-statistician/`** 🔓 — `UserController::handle_statistician_upgrade_endpoint`

⚠️ **Not actually authenticated.** The route array at `src/Routes/UserRoute.php:86-98` is built
positionally, so `[Auth::class, 'jwt']` becomes a numeric-keyed element instead of the
`'permission_callback'` value. WordPress skips the permission check when that key is empty, so this
endpoint is callable by anonymous callers. See
[known-issues.md](known-issues.md#post-upgrade-statistician-is-unauthenticated).

`user_id` (integer, required, validated numeric — this `args` map *is* correctly keyed, so it
applies).

Converts the user to the BuddyBoss member type `registered-statistician` and writes
`bp_profile_type` user meta. The only guard is `$user_id === 1`, so an unauthenticated caller can
promote any other account.

| Status | Response |
| --- | --- |
| 200 | `{ "success": true, "message": "User ID 2695 successfully converted to registered statistician." }` |
| 403 | Refuses `user_id === 1` |
| 404 | `{ "code": "not_found", ... }` unknown user |
| 400 | `user_id` missing or non-numeric |

## transactions

**`GET /transactions`** — `TransactionController::get_transactions`

WooCommerce order export. Returns `500 woocommerce_not_active` if WooCommerce is not loaded.

| Query param | Type | Default | Notes |
| --- | --- | --- | --- |
| `startdate` | string | — | `YYYY-MM-DD`; inclusive lower bound on `date_created` |
| `enddate` | string | — | `YYYY-MM-DD`; inclusive upper bound (`23:59:59`) |
| `status` | string | all statuses | Comma-separated, `wc-` prefix optional (`completed,processing`) |
| `per_page` | integer | 100 | Max 500 |
| `page` | integer | 1 | Min 1 |

⚠️ The route passes the arg schema as a callable (`'args' => [Class::class, "get_endpoint_args"]`)
rather than a map keyed by parameter name, so WordPress applies **no** validation, sanitisation or
min/max enforcement — the `[Class, 'method']` form is silently ignored. An invalid date silently
produces a different query, and `per_page=99999` is honoured.

Response:

```json
{
  "success": true,
  "total": 248,
  "page": 1,
  "per_page": 100,
  "total_pages": 3,
  "transactions": [ { "...": "see below" } ]
}
```

Headers `X-WP-Total` and `X-WP-TotalPages` are also set (the latter as a float, so it renders as
`3` or `3.5`).

Each `transactions[]` entry contains: `id`, `order_number`, `status`, `status_label`, `currency`,
`currency_symbol`; `dates{created,modified,completed,paid}`; `financials{subtotal,total,total_tax,
total_discount,shipping_total,…,amount_due,formatted_total}`; `payment{method,method_title,
transaction_id}`; `billing{first_name,…,address_formatted}`; `shipping{…}`; `customer{id,is_guest,
username,display_name,avatar_url,registered_date,total_orders,total_spent}`; `line_items[]` with a
nested `product{}` block; `shipping_lines[]`; `tax_lines[]`; `fee_lines[]`; `coupon_lines[]`;
`refunds[]`; `notes[]`; `meta_data[]` (keys starting with `_` filtered out); and a `buddyboss{}`
block with xprofile groups, member type, profile URL, avatar, groups and follower count.

Because BuddyBoss calls are guarded by `function_exists()`, the `buddyboss` key is **absent
entirely** when the component is unavailable — clients must not rely on it existing.

## conference-registrations

**`GET /conference-registrations`** — `ConferenceRegistrationController::get_transactions`

Reads a `{prefix}conference_registrations` table (external to this plugin — the payment provider
writes it). Declared `args` are correctly wired here.

| Query param | Type | Default | Notes |
| --- | --- | --- | --- |
| `registering_for` | string | `Conference Onsite + Preconference Onsite` | Exact match; the literal value `all` (case-insensitive) disables the filter |
| `year` | integer | current year | Must be 4 digits (1000–9999) or the request is rejected |
| `paymentstatus` | string | `paid` | Exact match on `payment_status` |

```json
{
  "status": "success",
  "total": 42,
  "filters": { "registering_for": "Conference Onsite + Preconference Onsite",
               "year": 2025, "paymentstatus": "paid" },
  "data": [ { "id": 1, "...": "raw table row" } ]
}
```

`data` is `SELECT *` — the raw rows, whatever columns the provider created. Ordered by
`registration_date DESC`. `500 table_not_found` if the table is absent.

## prod/all-products

**`GET /prod/all-products`** — `ProductController::getAllProducts`

No parameters. Returns a **bare array** of every WooCommerce product in any status
(`publish`, `draft`, `pending`, `private`):

```json
[ { "id": 5035, "name": "2025 Annual Dues", "price": "150.00", "sku": "DUES-2025", "image": "https://..." } ]
```

## prod/bought-product

**`GET /prod/bought-product`** — `ProductController::checkWhetherProductWasPurchasedByUser`

⚠️ Registered as `GET`, reads the JSON body: `{ "user_id": 2695, "product_id": 5035 }`.

```json
{ "member_id": "2695", "product_id": 5035, "has_bought": true, "status": "success" }
```

Wraps `wc_customer_bought_product()`. The guard is
`if (!$user_id | $product_id === 0)` — a bitwise OR, so it only 404s when `user_id` is falsy;
a missing `product_id` slips through and queries product 0.

## prod/all-orders

**`GET /prod/all-orders`** — `ProductController::getOrders`

No parameters. **Bare array** of every `processing` and `completed` order, refunds excluded:

```json
[ { "order_id": 12345, "first_name": "...", "surname": "...", "email": "...",
    "phone": "...", "items": [ { "product_id": 5035, "product_name": "...",
                                "quantity": 1, "total": "150.00" } ],
    "total": "150.00", "status": "completed", "date_paid": "2025-02-01 10:00:00",
    "payment_method": "Direct Bank Transfer", "transaction_id": "...",
    "billing_state": "Lagos" } ]
```

Unpaginated — `limit => -1`. Cost grows linearly with order count.

## cert/next-number

**`GET /cert/next-number`** — `CertController::getNextCertNumber`

```json
{ "next_cert_number": 42, "status": "success" }
```

⚠️ This uses `cison_get_next_cert_number()` from `Utils/Certificate.php`, which computes
`COUNT(*) + 1` across **all** years. `POST /cert/add-new` uses a completely different, lock-guarded
`MAX(CAST(SUBSTRING_INDEX(cert_id,'-',-1))) + 1` scoped to the current year. The two disagree, and
`Utils/Certificate.php` is never loaded by the autoloader.

## cert/add-new

**`POST /cert/add-new`** — `CertController::addNewCertification`

Body: `user_id` **or** `member_id` (at least one required). If only `member_id` is given, the user is
resolved via xprofile field 894.

The handler is the most carefully written one in the plugin:

1. Resolves `user_id`; `404 not_found` if unknown.
2. Rejects if a certificate row exists **and** the PDF exists on disk — `400 certificate_exists`.
3. Reads xprofile fields 1595 (Is Transiting), 1 / 864 / 2 (first / middle / surname) and the user
   email. `member_type` becomes `transiting` or `inducted`.
4. Takes a **named MySQL advisory lock** `GET_LOCK('cison_cert_id_lock', 10)`; `503 lock_timeout`
   on failure.
5. Derives the next `YYYY-NNNNN` sequence with `MAX(CAST(SUBSTRING_INDEX(cert_id, '-', -1)))`
   scoped to `CISON_CURRENT_YEAR`, then probes forward (max 100 attempts) for a free ID;
   `500 cert_id_exhausted` if none.
6. Inserts the row and always releases the lock.
7. Returns `500 db_error` with `$wpdb->last_error` on failure.

```json
{ "user_id": 2695, "cert_id": "2026-00042",
  "certificate_path": "/var/www/wp-content/private/certificates/certificate_2026-00042.pdf",
  "status": "success", "message": "Certificate record created successfully" }
```

⚠️ It writes the **database row only** — no PDF is generated. `certificate_path` will not exist on
disk until something else creates it. Also note the column order in the `$wpdb->insert()` format
array does not match the `$data` key order, so values are coerced against the wrong formats.

## cert/single-certificate

**`GET /cert/single-certificate`** — `CertController::singleCertificate`

Params: `user_id` or `member_id` (this one reads `get_params()`, so **query string works**).

`{ "data": { ...certificate row... }, "status": "success" }`
`400 invalid_id` if neither param, `404 not_found` if no row or no resolvable user.

## cert/remove-cert

**`DELETE /cert/remove-cert`** — `CertController::remove_certificate`

Params: `user_id` or `member_id` (query string). Deletes the row from the certificate table.

```json
{ "data": "Successfully removed certificate", "status": "success" }
```

Does **not** delete the PDF from disk, leaving the file orphaned.

## cert/list-cert

**`GET /cert/list-cert`** — `CertController::list_certificates`

No parameters. `{ "data": [ ...all certificate rows... ], "status": "success" }`. Unpaginated.

## cert/get-qualified-candidate

**`GET /cert/get-qualified-candidate`** — `CertController::get_qualified_certificates`

No parameters. Walks every `wp_users` row, keeps transiting members with exactly one paid fee
(`Money::getArrayCount($paid) === 1`) and no existing certificate row, and returns their profile data:

```json
{ "data": [ { "user_id": 2695, "member_id": "20260261", "is_transiting": true,
              "first_name": "...", "profile_type": "Transiting Member",
              "user_email": "..." } ], "status": "success" }
```

`SELECT * FROM wp_users` plus ~190 queries per candidate. Unpaginated and expensive.

⚠️ `reg_year` is computed with `max(2024, min((int) substr($member_id, 0, 4), 2025))` — hardcoded
year bounds that do not track `CISON_CURRENT_YEAR`, so the result is wrong outside 2024–2025.

## cert/add-2025-conference

**`POST /cert/add-2025-conference`** — `CertController::add2025Conference`

Body: identical in shape to
[`/cert/add-2025-preconference`](endpoints/add-2025-preconference.md) — `order_id`, `member_id`,
`first_name`, `surname`, `item_name`, `item_price`, `order_total`, `status`, `paid_date`, `email`,
`phone`, `payment_method`, `transaction_id`, `order_link`, `billing_state`, `cert_name`. Inserts
into `{prefix}cison_conference_2025` and builds `cert_url` from
`content_url('private/conference/…')`.

```json
{ "status": "success", "message": "Conference registration saved successfully" }
```

It shares the body schema and the name requirement with its pre-conference sibling:
`CertController::get2025RegistrationArgs()` is declared on both routes, and both handlers call
`CertController::getRequiredNames()`. `email`, `first_name` and `surname` are therefore **required**
here too, and every other field is guarded and cast.

⚠️ It still differs from the pre-conference endpoint in ways that are **not** fixed:

- writes a Unix epoch integer into the `timestamp` column `last_updated`, which is invalid for
  that type and fails under MySQL strict mode;
- its `$data` array has 17 keys against 15 format entries, so `cert_url` and `last_updated` are
  unformatted;
- returns `'Failed to save pre-conference record'` on failure — a copy-paste slip;
- the duplicate-email check is written **twice** (`CertController.php:261` and `:266`, the second
  unreachable);
- `$cert_id` / `$cert_url` are computed at `:279`/`:283` and never stored, and `$file_url` is stored
  as `cert_url` while `$file_path` is computed and discarded.

Money uses `%s` rather than `%f`, so cents are not truncated — but `order_id` is not int-cast either.



## cert/add-2025-preconference

**`POST /cert/add-2025-preconference`** — `CertController::add2025PreConference`

`email`, `first_name` and `surname` are required; every other field is optional and defaults to an
empty value. The body schema is declared on the route, so WordPress validates types and the
handler's `sanitize_text_field()` / `sanitize_email()` / `sanitize_file_name()` calls run before the
insert. The name fields reject a value that is blank once trimmed, which `required: true` alone
would not catch.

Note the parameter is `surname`, written to the `last_name` column; sending `last_name` is rejected.

```json
{ "status": "success",
  "message": "Pre-conference registration saved successfully",
  "id": 42,
  "cert_url": "https://example.com/wp-content/private/preconference/cert_ada.pdf" }
```

→ **See the full reference: [endpoints/add-2025-preconference.md](endpoints/add-2025-preconference.md).**

## cert/get-2025-preconference

**`GET /cert/get-2025-preconference`** 🔓 — `CertController::get2025Preconference`

No parameters, **no authentication**. Returns a **bare array**:

```json
[ { "id": 1, "first_name": "Ada", "last_name": "Lovelace", "email": "ada@example.com",
    "cert_url": "https://example.com/wp-content/private/preconference/cert_ada.pdf" } ]
```

Unpaginated `SELECT id, first_name, last_name, email, cert_url`. This publishes the name, e-mail
address and certificate URL of **every pre-conference registrant** to unauthenticated callers.

## cert/get-2025-conference

**`GET /cert/get-2025-conference`** 🔓 — `CertController::get2025Conference`

Same shape as above, reading `{prefix}cison_conference_2025`. Also unauthenticated.

## cert/drop-conference-tables

**`GET /cert/drop-conference-tables`** — `CertController::dropTables`

⚠️ **Destructive.** Executes `DROP TABLE IF EXISTS` against both
`{prefix}cison_preconference_2025` and `{prefix}cison_conference_2025`, deleting every conference
and pre-conference registration. JWT-protected, but the permission callback grants access to any
allow-listed token holder, and it is reachable by `GET`, so any prefetching link or crawler that
carries the token destroys the data.

```json
{ "message": "Tables dropped successfully" }
```

## data/get-all

**`GET /data/get-all`** — `DataController::allUsers`

Registered from `CertRoute.php:64`, not `DataRoute.php`. Returns
`{ "data": [ ... ], "status": "success" }` where each object is keyed by **raw xprofile field ID**
(`"1": "Ada"`, `"2": "Lovelace"`, …) across a hardcoded list of ~190 field IDs. `user_id` and
`user_email` are **not** included, so rows cannot be correlated to accounts.

⚠️ ~190 BuddyPress queries per user. This endpoint will time out or exhaust memory on any
non-trivial dataset.

## data/users/…-payment

Six endpoints in three pairs. All take **no parameters** and return
`{ "data": [ ... ], "status": "success" }` with each element being
`DataController::get_userdata()` plus `user_email` and a `fees` block.

`get_userdata()` returns: `user_id`, `member_id`, `is_transiting`, `first_name`, `middle_name`,
`last_name`, `phone_number`, `certificate_name` (field 1611), `profile_type`.

| Path | Handler | Selection |
| --- | --- | --- |
| <a id="datauserscomplete-payment"></a>`/data/users/complete-payment` | `users_with_cleared_payments_2025_limit` | Registered on or before `2025-12-31`, all required fees paid |
| <a id="datauserspartial-payment"></a>`/data/users/partial-payment` | `users_with_partial_payments_2025_limit` | Same, mixed paid/unpaid |
| <a id="datausersno-payment"></a>`/data/users/no-payment` | `users_without_payments_2025_limit` | Same, no required fees paid; **skips members with no `member_id`** |
| <a id="datauserscomplete-payment-latest"></a>`/data/users/complete-payment-latest` | `users_with_cleared_payments` | No date filter; adds `is_transiting`, `reg_year`, `profile_type`, `dob` (field 561) |
| <a id="datauserspartial-payment-latest"></a>`/data/users/partial-payment-latest` | `users_with_partial_payments` | No date filter |
| <a id="datausersno-payment-latest"></a>`/data/users/no-payment-latest` | `users_without_payments` | No date filter; skips members with no `member_id` |

```json
{ "data": [ { "user_id": 2695, "member_id": "20260261", "is_transiting": true,
              "first_name": "...", "middle_name": "...", "last_name": "...",
              "phone_number": "...", "certificate_name": "...", "profile_type": "Transiting Member",
              "user_email": "...",
              "fees": { "paid":   { "annual_dues_2025": true },
                        "unpaid": { "dev_levy_2025": { "name": "Development Levy 2025",
                                                        "product_ids": [5063] } } } } ],
  "status": "success" }
```

Classification is `Money::getArrayCount($paid)`: `1` = all paid (complete), `0` = mixed
(partial), `-1` = none paid (no payment). ⚠️ "Partial" collapses *one* unpaid fee and *five*
unpaid fees into the same bucket.

⚠️ The `_2025_limit` variants use `Money::cison_get_required_fees_till_2025()`, the
`-latest` variants call the **global** `cison_get_required_fees()` with 4 arguments — a function
that **does not exist in this repository** (only the 2-argument
`Money::cison_get_required_fees()` static method does). The three `-latest` endpoints therefore
fatal unless another plugin provides the global wrappers.

## data/users/education

**`GET /data/users/education`** — `DataController::getEducationForUserID`

⚠️ `GET` route reading the **JSON body**: `{ "user_id": 2695 }`.

Returns every field of the BuddyPress group named `education`, keyed by **field name** rather than
ID, plus the raw group objects:

```json
{ "data": { "University": "University of Lagos", "Department": "Chemistry" },
  "group": [ { "id": 3, "name": "Education", "...": "..." } ],
  "status": "success" }
```

`400` → `{ "data": [], "status": "error", "message": "Invalid or missing user_id" }`.

The group loop `break`s on the first case-insensitive `education` match, so only one group is ever
returned. The `group` key serialises live `BP_XProfile_Group` objects, which can include internal
properties.

## learn/registration

**`GET /learn/registration`** — `LearnController::get_user_details_from_memberid`

⚠️ `GET` route reading the **JSON body**: `{ "member_id": "20260261" }`.

```json
{ "user_id": 2695, "first_name": "Ada", "last_name": "Lovelace",
  "middle_name": "", "member_id": "20260261",
  "email": "ada.lovelace@example.com",
  "username": "ada.lovelace@example.com" }
```

| Status | Message |
| --- | --- |
| 200 | payload above |
| 500 | `MemberID is not Valid` (the bare `WP_Error` has no status, so it defaults to 500) |
| 404 | `User not found for Member ID: {member_id}` |

## certification/create

**`POST /certification/create`** — `CertificationController::handle_create_certificate`

Multipart form (`multipart/form-data`) writing to `{prefix}cert_registry` — a **different table**
from the certificate table used by `/cert/*`.

| Field | Required | Notes |
| --- | --- | --- |
| `certificate_file` | yes | File upload |
| `user_email` | yes | Resolved to `user_id`; ⚠️ no null check — unknown email inserts with `user_id = 0` |
| `user_name` | yes | |
| `cert_key` | yes | Becomes the row's `key` column |
| `hmac_key` | yes | Must equal `hash_hmac('sha256', cert_key, SSSECURE_AUTH_KEY)` |
| `name` | no | Default `Membership Certificate`; also the **upload folder name** |
| `template_id` | no | |
| `is_main` | no | Marks the primary certificate |
| `date_expiry` | no | |

`201 Created` returns the inserted row plus `id`, `key`, `hmac` and `file_url`.

⚠️ `$file_url` is set to `$upload_result['file_path']` — an **absolute filesystem path** — and the
actual URL is discarded, so the response and the stored column contain a server path, not a URL.
File storage is redirected to `wp-content/uploads/<name>/` via an `upload_dir` filter, i.e. a
**publicly served** directory.

## certification/upload

**`POST /certification/upload`** — `CertificationController::handle_upload_certificate`

Multipart `certificate_file`, required. `200`:

```json
{ "success": true, "file_name": "cert.pdf",
  "file_path": "/var/www/wp-content/private/certificates/cert.pdf",
  "file_url": "https://example.com/wp-content/private/certificates/cert.pdf" }
```

⚠️ Uses raw `move_uploaded_file()` — no `wp_handle_upload()`, no MIME or extension allow-list, and
it returns the absolute server path. Files land in `wp-content/private/certificates/`, a path
`content_url()` still serves unless `.htaccess` blocks it.

## certification/update

**`PUT /certification/update`** — `CertificationController::handle_update_certificate`

| Field | Required | Notes |
| --- | --- | --- |
| `id` | yes | Row to update |
| `certificate_file` | no | Replacement file; re-hashes with `wp_hash(cert_key\|user_id\|new_url, 'nonce')` |
| `is_main` | no | |
| `date_expiry` | no | |

`{ "success": true, "updated_id": 12 }` · `404 not_found` · `400 bad_request` when nothing to
change · `500 upload_error`.

## certification/delete

**`DELETE /certification/delete?id=12`**

`{ "deleted": true, "id": 12 }` · `404 not_found` if the row is absent.

⚠️ Deletes the database row only — the uploaded file and the WordPress attachment are left behind.
