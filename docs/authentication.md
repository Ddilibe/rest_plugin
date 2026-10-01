# Authentication

The plugin uses a hand-rolled HS256 JWT implementation. There are two supported ways to
authenticate against a protected route, and one endpoint family that is deliberately public.

## Two accepted credentials

`SRC\Middleware\Auth::jwt()` accepts **either**:

1. **A logged-in WordPress session** — `$current_user->ID != 0`, i.e. a valid WordPress login
   cookie. This is what a browser session hitting the API from the front end uses.
2. **A Bearer JWT** — `Authorization: Bearer <token>` decoded and validated by `SRC\Utils\Jwt`.

```php
// src/Middleware/Auth.php
public static function jwt($request)
{
    $current_user = wp_get_current_user();

    if ($current_user->ID != 0) {
        return true;                                   // cookie / logged-in user
    }

    $auth = $request->get_header('authorization');
    if (!$auth || !str_starts_with($auth, 'Bearer ')) {
        return false;                                  // → 401 rest_forbidden
    }

    $token = trim(str_replace('Bearer', '', $auth));
    return !is_wp_error(Jwt::decode($token));
}
```

Note that a failing permission callback produces WordPress' standard
`rest_forbidden` / HTTP 401 response, not a descriptive error.

## Public (unauthenticated) routes

These use `'permission_callback' => '__return_true'`:

| Route | Method | File |
| --- | --- | --- |
| `/cison/v1/hello` | GET | `HelloRoute.php:14` |
| `/cison/v1/auth/api-key` | GET, POST | `AuthRoute.php:17` |
| `/cison/v1/auth/register` | POST | `AuthRoute.php:24` |
| `/cison/v1/docs` | GET | `main.php:162` |
| `/cison/v1/cert/get-2025-preconference` | GET | `CertRoute.php:49` |
| `/cison/v1/cert/get-2025-conference` | GET | `CertRoute.php:55` |

> **`GET /cert/get-2025-preconference` and `/cert/get-2025-conference` are unauthenticated and
> return attendee name, email address and certificate URL for every registrant.** Treat them as
> public data endpoints; if that is not intended, change them to `[Auth::class, 'jwt']`.

## Obtaining a token

### `POST /cison/v1/auth/api-key` → `AuthController::login()`

Despite the name, this endpoint issues a JWT and takes **no password**. It requires the caller to
present an email address that appears in the `ACCEPTED_USERS` allow-list *and* exists as a
WordPress user.

Request:

```json
{ "email": "staff@example.com" }
```

Responses:

| Status | Body | Condition |
| --- | --- | --- |
| 200 | `{ "status": "success", "data": { "token": "<jwt>" } }` | email is allow-listed and exists |
| 400 | `{ "code": "api_error", "message": "Email is required", "data": { "status": 400 } }` | no email |
| 403 | `{ "code": "api_error", "message": "Access denied", "data": { "status": 403 } }` | email not in `ACCEPTED_USERS` |
| 401 | `{ "code": "api_error", "message": "Invalid credentials", "data": { "status": 401 } }` | allow-listed but no such WordPress user |

Token payload (`AuthController.php:43`):

```json
{
  "iss": "cison",
  "sub": 1234,
  "iat": 1767225600,
  "email": "staff@example.com",
  "exp": 1767229200
}
```

- `sub` — the WordPress user ID (integer)
- `exp` — `time() + 3600` (one hour, `Jwt::EXPIRY`)
- `iss` — always `cison` (`Jwt::ISSUER`)

The route also accepts `GET`, in which case `get_json_params()` returns null and the request fails
with `400 Email is required`. Use `POST`.

### `POST /cison/v1/auth/register` → `AuthController::create()`

Creates a minimal `wp_users` row for an allow-listed email address. It writes only
`user_login`, `user_email` and `display_name` — no password hash, no role, no BuddyPress profile.
Intended for seeding a WordPress account for an allow-listed staff email before `login` can work.

Request: `{ "email": "...", "name": "..." }`

| Status | Message |
| --- | --- |
| 200 | `{ "status": "success", "data": { "name": "...", "email": "..." } }` |
| 400 | `Email and name are required. This is what I got {email} and {name}` |
| 400 | `Invalid email format` |
| 403 | `Access denied` (not allow-listed) |
| 409 | `User already exists` |
| 500 | `Failed to create user` |

> The 400 message interpolates the submitted values into the error text. Do not send anything
> sensitive to this endpoint; it echoes the input back in the response body.

## Token validation

`SRC\Utils\Jwt::decode()` performs four checks, in this order:

1. **Structure** — the token must split into exactly three `.`-separated parts, else
   `jwt_invalid` / `Invalid token`.
2. **Signature** — `hash_equals()` against a freshly computed HMAC-SHA256 using
   `JwtConfig::SECRET`, else `jwt_invalid` / `Invalid signature`.
3. **Expiry** — `$data['exp'] < time()`, else `jwt_expired` / `Token expired`.
4. **Allow-list** — `$data['email']` must be in `ACCEPTED_USERS`, else
   `Access denied` / `You are not allowed to access this service`.

Steps 1–3 are standard. Step 4 means the allow-list is re-checked on **every** request, so
removing an email from `ACCEPTED_USERS` invalidates its outstanding tokens immediately (they become
`401` on the next call, since `Auth::jwt()` only checks `!is_wp_error()`).

Decode returns the decoded payload array, not a user object. No code path uses the payload's `sub`
to establish a WordPress identity, so a valid token does **not** make the request "logged in" for
WordPress core — only for the plugin's own `permission_callback`.

### Implementation notes

```php
// src/Utils/Jwt.php
$header  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
$payload = base64_encode(json_encode($payload));
$signature = hash_hmac('sha256', "$header.$payload", JwtConfig::SECRET, true);
return "$header.$payload." . base64_encode($signature);
```

- Base64 uses PHP's `base64_encode()`, i.e. **standard alphabet with `+`/`/` and `=` padding** —
  not the URL-safe `base64url` alphabet mandated by RFC 7515. Tokens are therefore safe to put in an
  `Authorization` header, but not in a URL query string or a path segment.
- The `alg` in the header is never read; the decoder always assumes HS256, so there is no
  algorithm-confusion attack surface here. It does mean the header is decorative.
- `src/Config/Jwt.php` ships `SECRET = 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET'`. **This must be
  overridden** — see [configuration.md](configuration.md#secrets).

## The `ACCEPTED_USERS` allow-list

Every authentication decision in the plugin resolves through this one value:

```php
$acceptedUsers = (array) Config::get('ACCEPTED_USERS', []);
if (!in_array($email, $acceptedUsers, false)) { /* deny */ }
```

`Config::get()` checks, in order: a PHP constant, then `getenv()`, then the supplied default.
Because the default is `[]` and the value is compared with a **loose** comparison (`false` third
argument to `in_array`), an unset `ACCEPTED_USERS` denies every login and invalidates every token.

Set it in `wp-config.php` before the plugin loads, as a PHP array constant:

```php
define('ACCEPTED_USERS', array(
    'admin@example.com',
    'registrar@example.com',
));
```

## Unused middleware

`SRC\Middleware\RateLimit::limit($request, $max = 60)` is a transient-based per-IP limiter
(60 requests / 60 seconds, keyed `rate_<md5(ip)>`). **No route registers it**, so it is currently
dead code. It also returns a `WP_Error` rather than a boolean, which is not what a
`permission_callback` should return.

`SubmitRoute` references `[Auth::class, 'api_key']`, a method that does not exist on
`SRC\Middleware\Auth`. `POST /cison/v1/submit` therefore fatals during its permission check.
See [known-issues.md](known-issues.md#submit-endpoint-fatal).
