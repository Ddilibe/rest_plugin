# `POST /cert/add-2025-preconference`

Records a paid 2025 CISON Pre-Conference registration. This is the write endpoint used by the
payment provider / front-end checkout flow; `GET /cert/get-2025-preconference` reads the rows back.

| | |
| --- | --- |
| **Method** | `POST` |
| **Path** | `/cison/v1/cert/add-2025-preconference` |
| **Full URL** | `https://<site>/wp-json/cison/v1/cert/add-2025-preconference` |
| **Route declaration** | `src/Routes/CertRoute.php:40-45` |
| **Handler** | `SRC\Controllers\CertController::add2025PreConference()` — `src/Controllers/CertController.php:463-563` |
| **Body schema** | `SRC\Controllers\CertController::get2025RegistrationArgs()` — shared with `/add-2025-conference` |
| **Required-field sanitiser** | `SRC\Controllers\CertController::sanitizeRequiredText()` / `getRequiredNames()` |
| **Permission callback** | `SRC\Middleware\Auth::jwt` — JWT bearer token **or** a logged-in WordPress session |
| **Content type** | `application/json` (the handler reads `$request->get_json_params()`) |
| **Table written** | `{prefix}cison_preconference_2025` |
| **Rate limit** | None — `RateLimit` is not wired to any route |
| **Success status** | `200 OK` (kept as `200` rather than `201` so existing callers do not break) |
| **Idempotency** | One row per e-mail address. The second call with the same address returns `400 already_exists`. |

## Request

### Body fields

The body is declared on the route, so WordPress validates it before the handler runs. **`email`,
`first_name` and `surname` are required**; everything else is optional and defaults to an empty
value.

| Field | Type | Required | Sanitised as | Notes |
| --- | --- | --- | --- | --- |
| `email` | string | **yes** | `sanitize_email` | Registrant's e-mail. Also the de-duplication key. |
| `order_id` | integer | no | `absint` | Payment order reference. Defaults to `0`. |
| `member_id` | string | no | `sanitize_text_field` | CISON membership number, e.g. `"20260261"`. |
| `first_name` | string | **yes** | rejects blank | Must be non-empty after trimming. |
| `surname` | string | **yes** | rejects blank | Must be non-empty after trimming. Written to the **`last_name`** column. |
| `item_name` | string | no | `sanitize_text_field` | e.g. `"2025 Pre-Conference"`. |
| `item_price` | number | no | cast to `float` | Stored as `decimal(10,2)`. Cents are preserved. |
| `order_total` | number | no | cast to `float` | Stored as `decimal(10,2)`. Cents are preserved. |
| `status` | string | no | `sanitize_text_field` | Payment status, e.g. `"paid"`. |
| `paid_date` | string | no | validated, normalised | Any `strtotime()`-parsable value, or `YYYY-MM-DD HH:MM:SS` verbatim. Unparsable → `400`. |
| `phone` | string | no | `sanitize_text_field` | |
| `payment_method` | string | no | `sanitize_text_field` | e.g. `"Direct Bank Transfer"`. |
| `transaction_id` | string | no | `sanitize_text_field` | Gateway transaction reference. |
| `order_link` | string | no | `esc_url_raw` | URL back to the order; stored in a `text` column. |
| `billing_state` | string | no | `sanitize_text_field` | |
| `cert_name` | string | no | `sanitize_file_name` | **File name only** — the certificate file is not uploaded here. Must not contain `/`, `\` or `..`. See [Certificate URL](#certificate-url). |

> **`surname`, not `last_name`.** `surname` is the parameter name; it is written to the `last_name`
> column. Sending `last_name` instead is not a recognised field — WordPress drops it during
> sanitisation and, because `surname` is required, the request is rejected with
> `400 rest_invalid_param`. The conference sibling endpoint still uses the same `surname` key.

> **Blank is not enough.** `required: true` alone would still accept `"   "`, because WordPress
> validates *before* it sanitises. The names therefore use
> `sanitizeRequiredText()`, which sanitises and then rejects an empty result.

> Fields are sanitised on the way in *and* re-sanitised in the handler. The schema callbacks run in
> `WP_REST_Request::sanitize_params()`; the handler's own `sanitize_text_field()` calls keep it safe
> if the handler is ever invoked directly or the schema is bypassed.

### Example request

```bash
curl -X POST 'https://example.com/wp-json/cison/v1/cert/add-2025-preconference' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...' \
  -d '{
        "order_id": 12345,
        "member_id": "20260261",
        "first_name": "Ada",
        "surname": "Lovelace",
        "item_name": "2025 Pre-Conference",
        "item_price": "150.50",
        "order_total": "150.50",
        "status": "paid",
        "paid_date": "2025-02-01 10:00:00",
        "email": "ada.lovelace@example.com",
        "phone": "+2348000000000",
        "payment_method": "Direct Bank Transfer",
        "transaction_id": "TRX-99213",
        "order_link": "https://example.com/my-account/orders/12345/",
        "billing_state": "Lagos",
        "cert_name": "certificate_2025_preconf_12345.pdf"
      }'
```

Without a token, but with a WordPress login cookie, the same call works from an authenticated
browser session (the `Auth::jwt` callback checks `wp_get_current_user()` first).

### Minimal request

`email`, `first_name` and `surname` are the only required fields:

```json
{
  "email": "ada.lovelace@example.com",
  "first_name": "Ada",
  "surname": "Lovelace"
}
```

This succeeds and inserts a row where every other text column is `''`, `item_price` and
`order_total` are `0.00`, `order_id` is `0`, `paid_date` is `NULL` and `cert_url` is `''` (an empty
string rather than a URL ending in a bare `/`).

### Rejected before the handler runs

WordPress rejects these with `400 rest_invalid_param` and the handler never executes:

| Body | Rejected because |
| --- | --- |
| `{"email": 123, …}` | `type: string` mismatch |
| `first_name` or `surname` absent | `required: true` |
| `"first_name": "   "` | Blank once trimmed |
| `{"cert_name": "../../evil.pdf"}` | `cert_name` must not contain path traversal |
| `{"cert_name": "uploads/evil.pdf"}` | `cert_name` must be a file name, not a path |
| `{"cert_name": "???"}` | Nothing survives `sanitize_file_name()` |
| `{"paid_date": "not-a-date"}` | Not parsable by `strtotime()` |
| *(no `email` at all)* | `required: true` |

## Responses

### `200` — success

```json
{
  "status": "success",
  "message": "Pre-conference registration saved successfully",
  "id": 42,
  "cert_url": "https://example.com/wp-content/private/preconference/certificate_2025_preconf_12345.pdf"
}
```

`id` and `cert_url` were added to the response; the original `status` and `message` values are
unchanged, so existing callers are unaffected. `cert_url` is `""` when `cert_name` was omitted.

### `400` — `rest_invalid_param`

Produced by WordPress from the route's `args` schema. See
[Rejected before the handler runs](#rejected-before-the-handler-runs).

```json
{ "code": "rest_invalid_param", "message": "cert_name must be a file name, not a path.", "data": { "params": { "cert_name": "..." }, "status": 400 } }
```

### `400` — `invalid_data`

`email` missing or emptied by `sanitize_email()`:

```json
{ "code": "invalid_data", "message": "Email is required", "data": { "status": 400 } }
```

`first_name` or `surname` missing or blank. These are also re-checked in the handler, so a direct
call to the handler is protected the same way. The message names the field:

```json
{ "code": "invalid_data", "message": "surname is required", "data": { "status": 400 } }
```

### `400` — `already_exists`

A row with the same e-mail already exists.

```json
{ "code": "already_exists", "message": "Record already exists for this email", "data": { "status": 400 } }
```

### `500` — `save_failed`

`$wpdb->insert()` returned false.

```json
{ "code": "save_failed", "message": "Failed to save pre-conference record", "data": { "status": 500 } }
```

The database error is **no longer** echoed to the caller. It is written to the PHP error log with
the `CISON:` prefix, so schema detail stays server-side:

```bash
grep 'CISON: Failed to save pre-conference' /var/log/php*-fpm.log
```

### `401` — unauthorised

Produced by WordPress, not by this handler, when the `permission_callback` returns `false`:

```json
{ "code": "rest_forbidden", "message": "Sorry, you are not allowed to do that.", "data": { "status": 401 } }
```

## Processing, step by step

Current source of `src/Controllers/CertController.php:463-563`, reproduced verbatim (dedented).
Carried without per-line numbers so this does not rot as the file moves:

```php
public static function add2025PreConference(WP_REST_Request $request)
{
    global $wpdb;

    $body = $request->get_json_params();

    if (!is_array($body)) {
        $body = [];
    }

    // Sanitise once, then use the sanitised value for both the duplicate
    // check and the insert. Previously the lookup used the sanitised value
    // while the raw request body was written to the row.
    $email = isset($body['email']) ? sanitize_email($body['email']) : '';

    if (empty($email)) {
        return new WP_Error('invalid_data', 'Email is required', ['status' => 400]);
    }

    // Enforced again here, not just in the route schema, so a direct call
    // cannot write a nameless registrant.
    $first_name = isset($body['first_name']) ? sanitize_text_field($body['first_name']) : '';
    $surname = isset($body['surname']) ? sanitize_text_field($body['surname']) : '';

    foreach (['first_name' => $first_name, 'surname' => $surname] as $field => $value) {
        if ($value === '') {
            // The param name, not a prose label: this error code carries no
            // 'params' map, so the message is the only place the caller can
            // learn which field was rejected.
            return new WP_Error('invalid_data', $field . ' is required', ['status' => 400]);
        }
    }

    $table_name = $wpdb->prefix . 'cison_preconference_2025';

    $existing = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE email = %s LIMIT 1",
            $email
        )
    );

    if (!empty($existing)) {
        return new WP_Error('already_exists', 'Record already exists for this email', ['status' => 400]);
    }

    $filename = '';

    if (isset($body['cert_name']) && $body['cert_name'] !== '') {
        $name_check = self::validateCertificateFileName($body['cert_name']);

        if (is_wp_error($name_check)) {
            return new WP_Error('invalid_data', $name_check->get_error_message(), ['status' => 400]);
        }

        $filename = self::sanitizeCertificateFileName($body['cert_name']);
    }

    $data = [
        'order_id' => isset($body['order_id']) ? absint($body['order_id']) : 0,
        'member_id' => isset($body['member_id']) ? sanitize_text_field($body['member_id']) : '',
        'first_name' => $first_name,
        'last_name' => $surname,
        'item_name' => isset($body['item_name']) ? sanitize_text_field($body['item_name']) : '',
        'item_price' => isset($body['item_price']) ? (float) $body['item_price'] : 0.00,
        'order_total' => isset($body['order_total']) ? (float) $body['order_total'] : 0.00,
        'status' => isset($body['status']) ? sanitize_text_field($body['status']) : '',
        'paid_date' => self::toMySqlDateTime(isset($body['paid_date']) ? $body['paid_date'] : ''),
        'email' => $email,
        'phone' => isset($body['phone']) ? sanitize_text_field($body['phone']) : '',
        'payment_method' => isset($body['payment_method']) ? sanitize_text_field($body['payment_method']) : '',
        'transaction_id' => isset($body['transaction_id']) ? sanitize_text_field($body['transaction_id']) : '',
        'order_link' => isset($body['order_link']) ? esc_url_raw($body['order_link']) : '',
        'billing_state' => isset($body['billing_state']) ? sanitize_text_field($body['billing_state']) : '',
        'cert_url' => $filename !== '' ? content_url('private/preconference/' . $filename) : '',
    ];

    // Positional, one entry per key in $data. "%f" for the money columns:
    // "%d" previously truncated 150.50 to 150.
    //
    // last_updated is deliberately omitted. The column is
    // TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, and
    // writing a Unix epoch integer into it is an invalid datetime that
    // fails outright under MySQL strict mode.
    $formats = ['%d', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

    $saved = $wpdb->insert($table_name, $data, $formats);

    if ($saved === false) {
        error_log('CISON: Failed to save pre-conference record: ' . $wpdb->last_error);

        return new WP_Error('save_failed', 'Failed to save pre-conference record', ['status' => 500]);
    }

    return rest_ensure_response([
        'status' => 'success',
        'message' => 'Pre-conference registration saved successfully',
        'id' => (int) $wpdb->insert_id,
        'cert_url' => $data['cert_url'],
    ]);
}
```

## Design notes

### One schema, two endpoints

`get2025RegistrationArgs()` is used by **both** `/add-2025-preconference` and
`/add-2025-conference`. The two endpoints record the same payload into structurally identical
tables, and when they each carried their own (or none) they silently drifted: the conference one
was left with no schema at all, so it accepted nameless, unsanitised registrants.

`getRequiredNames()` is likewise the single enforcement point both handlers call, so neither can
drift back into writing a row without a name.

### Why the schema is a map, not a callable

Both routes pass a plain map keyed by parameter name:

```php
'args' => CertController::get2025RegistrationArgs(),
```

This is the only form WordPress applies. `WP_REST_Request::has_valid_params()` iterates the
argument as `$key => $arg`:

```php
foreach ( $this->get_attributes()['args'] as $key => $arg ) {
    $param = $this->get_param( $key );
    ...
}
```

The `[ClassName, 'method']` callable form used in `UserRoute` and `ConferenceRegistrationRoute` is
therefore **silently ignored** — the loop never runs, no validation happens, and no error is
raised. It looks like validation but does nothing.

### `last_updated` is deliberately not written

`last_updated` is declared as

```sql
last_updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

The old code wrote a Unix epoch integer (`'last_updated' => time()`) with no matching format
specifier. MySQL does not interpret integers as epoch values in a `TIMESTAMP` column, so under
strict mode the insert failed outright with error 1292.

The column is now **omitted from the insert entirely**, so `DEFAULT CURRENT_TIMESTAMP` applies and
the `ON UPDATE` clause keeps it fresh on later writes. There is no value to cast and no format
entry to get out of step with the column list.

### Money uses `%f`

`item_price` and `order_total` are cast to `float` and formatted with `%f`. The previous `%d` ran
the values through `wpdb::process_field_formats()`'s `absint()`, so `"150.50"` was stored as
`150.00` and `"0.99"` as `0.00`.

`order_id` stays `%d` because it is a `bigint(20)` order reference.

### The format array is positional

`$wpdb` applies `$formats` positionally against `$data`, so the two arrays must be the same length
and in the same order. The handler builds both from one list, and the length is asserted by the
test suite. The previous code had 17 columns against 16 format entries.

This is why the `surname` → `last_name` mapping is not just a rename in the docs. `$data` is built
keyed by **column** name, and the key order happens to match the `CREATE TABLE` column order:

```sql
INSERT INTO `wp_cison_preconference_2025`
  (`order_id`, `member_id`, `first_name`, `last_name`, `item_name`, `item_price`, …)
VALUES
  (7, '', 'Grace', 'Hopper', '', 150.50, …);
```

`first_name` and `last_name` sit at indexes 2 and 3, both formatted `%s`. A `surname` key in
`$data` would be a column that does not exist and the insert would fail outright — which is
exactly why the value is mapped to `last_name` on the way in. There is no `surname` column.

`last_updated` is the one column intentionally absent from `$data`, so `DEFAULT CURRENT_TIMESTAMP`
applies.

### Certificate URL

```php
'cert_url' => $filename !== '' ? content_url('private/preconference/' . $filename) : '',
```

`cert_url` remains a **string concatenation**, not a filesystem check. The endpoint:

- does not verify the file exists,
- does not upload anything,
- does not write to `WP_CONTENT_DIR`.

The intended file location is `wp-content/private/preconference/{cert_name}`, expected to be
written by a separate upload step (see `POST /certification/upload`, which writes to
`wp-content/private/certificates/` — a different directory).

`cert_name` is validated as a **bare file name**. Validation deliberately inspects the raw value
rather than the sanitised one: `sanitize_file_name()` would quietly turn `../../evil.pdf` into
`evil.pdf`, hiding the caller's bug and returning a `cert_url` pointing at a different file than
the one that was asked for. A name, not a path, is the contract, so a path is rejected outright.

Because the URL is served by `content_url()`, anyone who can guess or obtain `cert_name` can fetch
the file. Put an `.htaccess` `Require all denied` / index.php stub in
`wp-content/private/preconference/` if these certificates are not public.

### Duplicate detection and race conditions

The check is a `SELECT` followed by an `INSERT` with no transaction and no unique constraint —
`email` is indexed as `KEY email (email)`, **not** `UNIQUE`. Two concurrent requests for the same
address can still both pass the `SELECT` and both insert. Closing that needs a schema change and a
transaction:

```sql
ALTER TABLE wp_cison_preconference_2025 DROP KEY email, ADD UNIQUE KEY email (email);
```

This was not applied automatically: if any existing duplicates are present the `ALTER` fails, and
deduplicating real registration data is a judgement call for whoever owns the data. Apply it after
inspecting:

```sql
SELECT email, COUNT(*) c FROM wp_cison_preconference_2025
 GROUP BY email HAVING c > 1;
```

The endpoint is one-row-per-**email**, not per-order: the same member registering for two different
orders, or two orders for the same member, share one row. The `order_id` index is non-unique, so
multiple rows per order are possible if the e-mails differ.

### Date handling

`toMySqlDateTime()` accepts:

- `YYYY-MM-DD HH:MM:SS` — passed through **untouched**, so existing callers' timestamps are not
  shifted by a timezone conversion,
- anything else `strtotime()` understands, converted via `wp_date('Y-m-d H:i:s', …)`,
- an empty value, which becomes `NULL`.

An unparsable value is rejected at the route with `400 rest_invalid_param` rather than being
silently written as `NULL` or a zero date.

## Column mapping

`$data` order matters — `$wpdb` applies `$formats` positionally against it.

| # | Body field | Column | Format | Type |
| --- | --- | --- | --- | --- |
| 1 | `order_id` | `order_id` | `%d` | `bigint(20) NOT NULL` |
| 2 | `member_id` | `member_id` | `%s` | `varchar(50)` |
| 3 | `first_name` | `first_name` | `%s` | `varchar(100)` |
| 4 | `surname` | **`last_name`** | `%s` | `varchar(100)` |
| 5 | `item_name` | `item_name` | `%s` | `varchar(255)` |
| 6 | `item_price` | `item_price` | `%f` | `decimal(10,2)` |
| 7 | `order_total` | `order_total` | `%f` | `decimal(10,2)` |
| 8 | `status` | `status` | `%s` | `varchar(20)` |
| 9 | `paid_date` | `paid_date` | `%s` | `datetime` |
| 10 | `email` | `email` | `%s` | `varchar(100) NOT NULL` |
| 11 | `phone` | `phone` | `%s` | `varchar(20)` |
| 12 | `payment_method` | `payment_method` | `%s` | `varchar(50)` |
| 13 | `transaction_id` | `transaction_id` | `%s` | `varchar(100)` |
| 14 | `order_link` | `order_link` | `%s` | `text` |
| 15 | `billing_state` | `billing_state` | `%s` | `varchar(50)` |
| 16 | `cert_name` → URL | `cert_url` | `%s` | `varchar(255)` |
| — | *(none)* | `last_updated` | *(not written)* | `timestamp` |

`certid` exists in the table but is **not** written by this endpoint. The full table definition is
in [data-model.md](../data-model.md#cison_preconference_2025).

## Reading the rows back

`GET /cert/get-2025-preconference` — **no authentication required**.

```bash
curl 'https://example.com/wp-json/cison/v1/cert/get-2025-preconference'
```

```json
[
  { "id": 1, "first_name": "Ada", "last_name": "Lovelace",
    "email": "ada.lovelace@example.com",
    "cert_url": "https://example.com/wp-content/private/preconference/certificate_2025_preconf_12345.pdf" }
]
```

It projects only `id, first_name, last_name, email, cert_url`, is unpaginated, and is public — see
[api-reference.md § cert/get-2025-preconference](../api-reference.md#certget-2025-preconference).

## Relation to the sibling endpoint

`POST /cert/add-2025-conference` is the same code against `{prefix}cison_conference_2025`. It now
shares the body schema and the name requirement. **Remaining differences:**

| Aspect | `add-2025-preconference` | `add-2025-conference` |
| --- | --- | --- |
| Table | `cison_preconference_2025` | `cison_conference_2025` |
| Folder in `cert_url` | `private/preconference/` | `private/conference/` |
| Success message | `Pre-conference registration saved successfully` | `Conference registration saved successfully` |
| Body declared on the route | yes | yes — same shared schema |
| Unguarded `$body['key']` access | no | no |
| Duplicate check | once | **twice** — the second is unreachable dead code (`CertController.php:266`) |
| Price formats | `%f` | `%s` — not truncated, but `order_id` is not int-cast either |
| `order_id` format | `%d` (int-cast) | `%s` |
| `last_updated` | omitted, default applies | Unix integer written, no format entry |
| `$data` / `$formats` lengths | 16 / 16 | 17 / 15 — `cert_url` and `last_updated` unformatted |
| `email` persisted | sanitised | sanitised |
| `cert_name` | validated as a bare file name | validated as a bare file name |
| Error message on failure | `'Failed to save pre-conference record'` (generic) | `'Failed to save pre-conference record'` — wrong wording for a conference |
| `id` / `cert_url` in response | both returned | neither |
| `order_id` column | `NOT NULL`, no default | `DEFAULT NULL` |

## Troubleshooting

| Symptom | Cause | Where to look |
| --- | --- | --- |
| `400 invalid_data` | `email` missing or emptied by `sanitize_email()` | Request body |
| `400 invalid_data` saying `first_name` / `surname` is required | Name missing or blank | Request body |
| `400 rest_invalid_param` naming `surname` | `last_name` sent instead of `surname` | Request body |
| `400 rest_invalid_param` | Schema validation — bad type, bad `cert_name`, unparsable `paid_date` | `data.params` names the field |
| `400 already_exists` | A row with that address exists | `SELECT id, email FROM wp_cison_preconference_2025 WHERE email = '…'` |
| `500 save_failed` with `Incorrect datetime value` | The `ALTER TABLE` migration never ran and `cert_url` is missing — `last_updated` itself is no longer written | Check the log line prefixed `CISON:`; see [data-model.md](../data-model.md#cison_preconference_2025) |
| `500 save_failed` with `Column 'cert_url' doesn't exist` | The `ALTER TABLE` migration never ran | `src/Models/PreConference_Model_2025.php:58-74`; needs `SELECT` on `INFORMATION_SCHEMA` |
| `500 table doesn't exist` on the `SELECT` | `dbDelta()` has not run | Triggered on `init` at priority 1; check for fatal errors earlier in `init` |
| `last_name` empty | Sent `last_name` instead of `surname` | Request body |
| `cert_url` empty | `cert_name` omitted | Request body |
| `paid_date` is `NULL` | `paid_date` was omitted or empty | Request body |
| `401 rest_forbidden` | No JWT and no login cookie, or the JWT is expired / the address is off the allow-list | [authentication.md](../authentication.md) |
| SQL errors in the PHP log | `$wpdb->show_errors()` is force-enabled in the certification controller | Not this endpoint — check `CertificationController` |

Verify the table and a stored row:

```sql
SHOW CREATE TABLE wp_cison_preconference_2025\G
SELECT id, order_id, member_id, first_name, last_name, item_name,
       item_price, order_total, status, paid_date, email, cert_url, last_updated
  FROM wp_cison_preconference_2025
 ORDER BY id DESC LIMIT 5;
```

## Outstanding recommendations

These are still open:

1. **Move the JWT secret off its placeholder** — `SRC\Config\Jwt::SECRET` is
   `'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET'` and is a class constant, so it is part of the
   repository. Anyone who can read the source can mint a token for any allow-listed address and
   write to this endpoint.
2. **Add `UNIQUE KEY email`** to close the `SELECT`-then-`INSERT` race, and wrap the pair in a
   transaction. See [Duplicate detection](#duplicate-detection-and-race-conditions) for the
   pre-flight query.
3. **Require authentication on `GET /cert/get-2025-preconference`** — it currently publishes the
   name, e-mail and certificate URL of every registrant to anonymous callers.
4. **Add a rate limit** to the write path via the existing but unwired `RateLimit` middleware.
5. **Apply the same fixes to `add-2025-conference`** — it still has every defect listed in the
   comparison table above.
6. **Check the certificate file exists** before storing its URL, and confirm
   `wp-content/private/preconference/` is not web-readable if the certificates are private.
