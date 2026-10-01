# Data Model

Tables created by the plugin, the WordPress/BuddyPress/WooCommerce tables it reads, and the
semantics of the fee-resolution logic in `SRC\Utils\Money`.

## Tables created by this plugin

`main.php:215-224` requires both model files and calls their `create_*_2025()` functions on
`init` at priority 1:

```php
add_action('init', function () {
    SRC\Models\create_preconference_model_2025();
    SRC\Models\create_conference_model_2025();
}, 1);
```

Both use `dbDelta()`, then hand-run `ALTER TABLE` statements guarded by an `INFORMATION_SCHEMA`
lookup — `dbDelta()` will not add a column to a table that already exists, so the migrations below
handle the two later additions.

`register_activation_hook(__FILE__, …)` is also present in both model files, but `__FILE__` is the
model file rather than the plugin file, and WordPress only honours activation hooks registered from
the main plugin file. **Those hooks never fire** — the `init` call is what actually creates the
tables.

Both functions run `dbDelta()` on **every** request, not once. `dbDelta()` is idempotent but
issues `DESCRIBE` round-trips each time; the `INFORMATION_SCHEMA` queries add two more per request.

## `cison_preconference_2025`

`{prefix}cison_preconference_2025` — created by `src/Models/PreConference_Model_2025.php`.
Written by `POST /cert/add-2025-preconference`, read by `GET /cert/get-2025-preconference`.

| Column | Type | Null | Default | Written by the API | Notes |
| --- | --- | --- | --- | --- | --- |
| `id` | `bigint(20)` | no | — | auto | Primary key |
| `order_id` | `bigint(20)` | **no** | — | ✅ `%d` | Payment order reference. `NOT NULL` with no default, unlike the conference table |
| `member_id` | `varchar(50)` | yes | `''` | ✅ `%s` | CISON membership number |
| `certid` | `varchar(100)` | yes | `''` | ❌ | Added by `ALTER` (`CertController.php`-side code expects it); indexed |
| `first_name` | `varchar(100)` | yes | `''` | ✅ `%s` | **Required** by `POST /cert/add-2025-preconference` |
| `last_name` | `varchar(100)` | yes | `''` | ✅ `%s` | Source field is `surname`, which is **required** by the same endpoint |
| `item_name` | `varchar(255)` | yes | `''` | ✅ `%s` | |
| `item_price` | `decimal(10,2)` | yes | `0.00` | ✅ `%f` | Float-cast on insert; cents preserved |
| `order_total` | `decimal(10,2)` | yes | `0.00` | ✅ `%f` | Float-cast on insert; cents preserved |
| `status` | `varchar(20)` | yes | `''` | ✅ `%s` | Payment status, e.g. `paid` |
| `paid_date` | `datetime` | yes | `NULL` | ✅ `%s` | Normalised to `Y-m-d H:i:s`; unparsable values rejected at the route |
| `email` | `varchar(100)` | **no** | — | ✅ `%s` | **Required** by the endpoint. De-duplication key, stored `sanitize_email()`-d. **No unique index** |
| `phone` | `varchar(20)` | yes | `''` | ✅ `%s` | |
| `payment_method` | `varchar(50)` | yes | `''` | ✅ `%s` | |
| `transaction_id` | `varchar(100)` | yes | `''` | ✅ `%s` | Gateway reference |
| `order_link` | `text` | yes | `NULL` | ✅ `%s` | |
| `billing_state` | `varchar(50)` | yes | `''` | ✅ `%s` | |
| `cert_url` | `varchar(255)` | yes | `''` | ✅ `%s` | Added by `ALTER`; `content_url('private/preconference/' . cert_name)`, or `''` when `cert_name` is omitted |
| `last_updated` | `timestamp` | yes | `CURRENT_TIMESTAMP` | *(not written)* | `ON UPDATE CURRENT_TIMESTAMP`. Omitted from the insert so the default applies |

Indexes: `PRIMARY KEY (id)`, `KEY order_id (order_id)`, `KEY member_id (member_id)`,
`KEY email (email)`, `KEY certid (certid)`.

**`status` is a free-text column and is not used for filtering anywhere.** The
`GET /cert/get-2025-preconference` reader ignores it entirely, so unpaid or refunded rows are
returned identically to paid ones.

## `cison_conference_2025`

`{prefix}cison_conference_2025` — created by `src/Models/Conference_Model_2025.php`. Structurally
identical to the pre-conference table, with two differences:

| Difference | Pre-conference | Conference |
| --- | --- | --- |
| `order_id` | `bigint(20) NOT NULL` | `bigint(20) DEFAULT NULL` |
| `item_price` / `order_total` defaults | `0.00` (quoted) | `0.00` (unquoted) |

Both tables are destroyed by `GET /cert/drop-conference-tables`.

## `CISON_CERT_TABLE`

The membership certificate table. Its **name is not defined by this plugin's schema** — it is
supplied externally:

| Source | Value |
| --- | --- |
| `Utils/Certificate.php:12` | `define('CISON_CERT_TABLE', 'wprx_cison_certificates')` |
| `CertController.php:14` | `define('CISON_CERT_TABLE', 'wprx_cison_certificates')` |
| `DataController.php:15` | `define('CISON_CERT_TABLE', Config::get('CISON_CERT_TABLE'))` |
| `UserController.php:16` | `define('CISON_CERT_TABLE', Config::get('CISON_CERT_TABLE', ''))` |

The observed table name in the codebase is `wprx_cison_certificates` (prefix `wprx_`), but the
controllers that read the constant do **not** apply `$wpdb->prefix`, so the value must be the fully
qualified name. The `Config::get()`-derived definitions pass the constant through unresolved if
`wp-config.php` set it to a prefixed name.

Columns inferred from the inserts and reads in `CertController::addNewCertification()`:

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `bigint(20)` | Primary key (implicit) |
| `user_id` | `bigint(20)` | WordPress user ID; joined against in `/validusers` |
| `member_id` | `varchar(50)` | |
| `cert_id` | `varchar(20)` | `YYYY-NNNNN`; e.g. `2026-00042` |
| `certificate_path` | `varchar(255)` | Absolute path under `CISON_CERTIFICATE_DIR` |
| `date_issued` | `int` | Unix timestamp |
| `secret_token` | `varchar(255)` | `wp_generate_password(12, false)` |
| `last_updated` | `int` | Unix timestamp |
| `firstname` | `varchar(100)` | xprofile field 1 |
| `middlename` | `varchar(100)` | xprofile field 864 |
| `surname` | `varchar(100)` | xprofile field 2 |
| `email` | `varchar(100)` | `wp_users.user_email` |
| `member_type` | `varchar(20)` | `transiting` or `inducted` |
| `cutoff_date` | `date` | Today for transiting members; applied cutoff otherwise |

## `{prefix}cert_registry`

Written and read by `src/Controllers/CertificationController.php` — **not** the same table as
`CISON_CERT_TABLE`. No `CREATE TABLE` for it exists in this repository; the controller checks
`information_schema` and returns `Table not found` if it is absent.

| Column | Notes |
| --- | --- |
| `id` | Primary key |
| `user_id` | Resolved from `wp_users.user_email`; ⚠️ inserted as `0` if the e-mail is unknown |
| `key` | The caller-supplied `cert_key` |
| `cert_hmac` | `wp_hash(key . '\|' . user_id . '\|' . file_url, 'nonce')` |
| `cert_name` | Also the upload folder name |
| `user_email`, `user_name` | |
| `template_id`, `is_main`, `date_expiry` | Optional |
| `file_url` | ⚠️ Stores an **absolute filesystem path** on create, a URL on update |
| `created_at` | `current_time('mysql')` |

## `{prefix}conference_registrations`

Read by `GET /conference-registrations`. Created and populated **outside this plugin** — by the
payment provider or a companion integration. The controller does `SELECT *` and projects nothing,
so its schema is whatever the writer created. Columns the controller references by name:

| Column | Used for |
| --- | --- |
| `registration_date` | `YEAR()` filter, `ORDER BY … DESC` |
| `registering_for` | Equality filter; `'all'` disables it |
| `payment_status` | Equality filter, default `paid` |

## BuddyPress xprofile field IDs

Magic numbers scattered through the controllers. They are site-specific and are **not** documented
in the codebase; the meanings below are inferred from usage.

| Field ID | Meaning | Used at |
| --- | --- | --- |
| `1` | First Name | `CertController.php:77`, `LearnController.php:33`, `DataController.php` |
| `2` | Last Name / Surname | `CertController.php:85`, `LearnController.php:41` |
| `5` | Phone Number | `DataController::get_userdata` |
| `561` | Date of Birth | `DataController::users_with_cleared_payments` (`dob`) |
| `864` | Middle Name | `CertController.php:81`, `LearnController.php:37` |
| `894` | **Member ID** | Member-number lookups throughout: `CertController.php:44`, `LearnController.php:23`, `UserController::getUserIDWithMemberId`, `Money::cison_get_paid_fees` |
| `1595` | **Is Transiting** — compared against the literal string `'Yes'` | `CertController.php:71`, `UserController`, `Money::cison_get_paid_fees` |
| `1611` | Certificate Name | `DataController::get_userdata` |

Member ID convention: the first four digits are treated as a registration year
(`substr($member_id, 0, 4)`), so an ID like `20260261` implies registration in 2026.

Confirm the mapping for a given site with:

```sql
SELECT field_id, name, type FROM wp_bp_xprofile_fields WHERE field_id IN (1,2,5,561,864,894,1595,1611);
```

## Other tables read

| Table | Via | Used by |
| --- | --- | --- |
| `wp_users` | `SELECT *` | `UserController::getAllUsers`, `CertController::get_qualified_certificates`, `DataController::allUsers`, `AuthController` |
| `wp_bp_xprofile_data` | `field_id` lookups | Member ID → user ID resolution, profile enrichment |
| `wp_bp_groups_members` | `SELECT *` | `UserController::getGroupMembers` |
| `wp_posts` / `wp_postmeta` | WooCommerce CRUD | Products, orders, order items |
| `wp_wc_orders*` | `WC_Order_Query` | `TransactionController`, `Money::cison_get_paid_fees` |

## Fee resolution

`SRC\Utils\Money` turns a member's registration year and transiting status into a set of required
fee keys, then marks each one paid or unpaid.

### Product ID maps

Hardcoded WooCommerce product IDs per year. There is no admin UI for these — changing a year's
dues price means editing `Money.php`.

| Year | Annual dues (regular / retired / student) | Development levy |
| --- | --- | --- |
| 2024 | `317` / `624` / `623` | `368` |
| 2025 | `5035` / `5983` / `5980` | `5063` |
| 2026 | `12110` / `12112` / `12114` | `12116` |

A year not in the map yields no product IDs.

### Required fees

`cison_get_required_fees($is_transiting, $reg_year)` returns `fee_key => ['name', 'product_ids']`:

- **Transiting** — `nsa_dues` (2023 NSA Membership Dues, product `885`) and `transition_fee`
  (NSA→CISON, product `366`), then `annual_dues_{Y}` and `dev_levy_{Y}` for 2024 through
  `CISON_CURRENT_YEAR`.
- **Not transiting** — `new_member_fee` (product `320`), then `annual_dues_{Y}` and
  `dev_levy_{Y}` from `clamp($reg_year, 2024, current_year)`.

### Paid fees

`cison_get_paid_fees($user_id)` returns `fee_key => bool`:

1. Resolves `is_transiting` (field 1595) and `reg_year` (first 4 digits of field 894).
2. Builds the required-fee list and initialises every key to `false`.
3. Inverts the map to `product_id => [fee_keys]`.
4. Marks a fee paid if a matching product appears on a subscription returned by
   `wcs_get_users_subscriptions()` with status `active` or `pending-cancel`, **or** on any order
   from `wc_get_orders(['customer_id' => …, 'status' => ['completed', 'processing']])`.
5. Matches on `product_id`, `variation_id`, and the product's `get_parent_id()`.
6. Caches the result in a transient `cison_paid_fees_{user_id}` for 15 minutes.

`cison_get_paid_fees_till_2025()` is the same against the year maps capped at 2025, cached under
`cison_paid_fees_2025{user_id}`.

### Unpaid fees

`cison_get_unpaid_fees($required, $paid)` — the required-fee entries whose `paid` flag is falsy.

### Classification

`Money::getArrayCount(array $arr): int` is the tri-state used by every `/data/users/*-payment`
endpoint:

| Return | Meaning | Endpoint bucket |
| --- | --- | --- |
| `1` | every value `true` | complete payment |
| `0` | mixed | **partial payment** |
| `-1` | every value `false` (including an empty array) | no payment |

`0` conflates "one fee outstanding" with "five fees outstanding" — the endpoints expose the full
`unpaid` map, so a client that needs the distinction must inspect it.

### Fee product selection by member type

From `src/docs/Membership.md` (working notes, not shipped code): when a member type maps to
several product IDs, the choice is made from `bp_profile_type` — `Student Member` takes index `2`,
everything else index `0`. Unpaid product IDs for a required-fee set are collected and turned into
payment links.

## Certificate file storage

| Constant | Path | URL |
| --- | --- | --- |
| `CISON_PRIVATE_DIR` | `wp-content/private/` | — |
| `CISON_CERTIFICATE_DIR` | `wp-content/private/certificates/` | — |
| `CISON_CERTIFICATE_URL` | — | `content_url('/private/certificates/')` |
| hardcoded, pre-conference | `wp-content/private/preconference/` | `content_url('private/preconference/…')` |
| hardcoded, conference | `wp-content/private/conference/` | `content_url('private/conference/…')` |
| `CertificationController::handle_upload_certificate` | `wp-content/private/certificates/` | `content_url('/private/certificates/…')` |
| `CertificationController::handle_create_certificate` | `wp-content/uploads/<cert name>/` | attachment URL |

The `wp-content/uploads/` location is a public web directory. `wp-content/private/` is served by
`content_url()` unless the web server is configured to deny it — add an `.htaccess`
(`Require all denied`) or a blank `index.html` to each `private/` subdirectory if the certificates
are confidential.
