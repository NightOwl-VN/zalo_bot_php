# Changelog

All notable changes to the **Zalo Bot PHP SDK** project will be documented in this file.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Initial public API**: PHP SDK for Zalo Bot Platform (bot.zapps.me), migrated from the Node.js [zalobot-sdk](https://github.com/NightOwl-VN/zalobot-sdk).
- `ZaloBot`: Entry point supporting both configuration array and `Config` object initialization, plus `fromEnv()` for auto-reading environment variables, and an optional second constructor parameter accepting any PSR-18 `ClientInterface` for custom HTTP clients.
- `ZaloClient`: HTTP client built on a PSR-18 `ClientInterface` with automatic exponential-backoff retry (GET/read requests only by default; opt-in for mutating requests), and comprehensive API error handling.
- `Config`: Strict configuration management and validation with typed properties, masked token display, and environment variable loading.
- PSR-4 autoloading under the `ZaloBot\Sdk\` namespace.

### Modules
- `MessageModule`: Send text messages (`sendText`), photos (`sendPhoto`), stickers (`sendSticker`), voice messages (`sendVoice`), chat actions (`sendChatAction`). Webhook management (`setWebhook`, `deleteWebhook`, `getWebhookInfo`, `testWebhook`) and bot info (`getMe`).
- `WebhookModule`: Token-based verification via `X-Bot-Api-Secret-Token` header using timing-safe `hash_equals` comparison (`verify`, `requireValid`), event parsing (`parseEvent` returns a plain array; `parseEventDto` returns the typed `WebhookEvent` DTO), generic handler callback (`handle`). Includes full `EVENT_MAP` for normalized event names.
- `UserModule`: Get user profiles (`getProfile`), list followers (`getFollowers`), check follow status (`isFollowing`). Built-in in-memory cache with 5-minute TTL and LRU eviction (`getProfileCached`, `clearCache`, `getCacheSize`).
- `MediaModule`: Upload images (`uploadImage`) and files (`uploadFile`), retrieve media URLs (`getMediaUrl`), download media to local path (`downloadMedia`) with SSRF protection.

### Error Handling
- `ZaloBotException`: Base exception with `getApiErrorCode()`, `getHttpStatus()`, `getDetails()`.
- `ApiException`: Zalo Bot API error responses.
- `AuthException`: Authentication errors for invalid or expired bot tokens.
- `RateLimitException`: Rate limit errors (HTTP 429) with `getRetryAfter()`.
- `WebhookException`: Webhook secret token verification failures.
- `ValidationException`: Input validation errors with `getField()`.
- `NetworkException`: Network connectivity errors.
- `TimeoutException`: Request timeout errors.

### Infrastructure
- `.env.example` for local development, standard PHP `.gitignore`.
- MIT License, contributing guide (`CONTRIBUTING.md`), Code of Conduct, Security Policy.
- API Reference documentation in English and Vietnamese (`docs/en/`, `docs/vi/`).
- PHPUnit test suite with unit tests for Config, WebhookModule, MessageModule, UserModule, and ZaloBot.
- GitHub Actions CI workflow with PHP 8.1/8.2/8.3/8.4 matrix, Dependabot for Composer, issue/PR templates.

### Changed
- **`ZaloClient` HTTP stack**: `ZaloClient` now accepts any **PSR-18** `ClientInterface`
  via constructor injection (optional 5th arg, defaults to a Guzzle client for
  backward compatibility). No client is instantiated per request anymore.
- **Exception classification**: `catch (\Throwable)` no longer converts every unknown
  error into `NetworkException`. Only PSR-18 `ClientExceptionInterface` transport
  errors are translated; `TypeError`/`Error`/`LogicException`/
  `InvalidArgumentException`/`RuntimeException` from programming mistakes or a
  broken mock propagate unchanged. Status codes map precisely:
  `401/403 → AuthException`, `429 → RateLimitException` (with `Retry-After` support),
  other 4xx/5xx → `ApiException`, transport-level errors → `NetworkException`,
  actual timeouts → `TimeoutException`. Previous exception chain always preserved.
- **Timeout detection**: Guzzle timeouts are now detected by exception type and
  handler context (`ConnectException`/`RequestException` with `errno` 28 or
  `timed_out` context), with a narrow message-pattern fallback only for handlers
  that don't provide context. `JsonException` thrown by request encoding is
  translated semantically into an `ApiException` with the JSON error preserved.
- **Retry policy**: retries on `408`, `429`, `502`, `503`, `504` for **GET/read
  requests only by default** — mutating `POST`/upload requests are never
  auto-retried (idempotency safety). Opt in with the new backward-compatible
  `retryMutations: true` constructor parameter. Exponential backoff with jitter,
  honors `Retry-After` header. `400/401/403/404/422` are never retried.
- **`MediaModule::downloadMedia()`**: now uses the injected PSR-18 client instead of raw
  cURL with `CURLOPT_FOLLOWLOCATION`; redirects are **rejected** (never auto-followed
  — a redirect target could be a host that never passed SSRF validation). Bytes
  are streamed to a temp file in the destination directory and atomically renamed,
  so partial files never appear at the final path. Destination directory and
  path-not-directory are validated up front.
- **SSRF hardening** (MediaModule): blocked IPv4-mapped IPv6 (`::ffff:127.0.0.1`),
  decimal IPs (`2130706433`), hex (`0x7f000001`) and octal (`0177.0.0.1`) forms,
  link-local (fe80::/10), ULA (fc00::/7), `localhost`/`.localhost`, and DNS-resolved
  hostnames that resolve to private addresses (DNS-rebinding defense).
- **Multipart upload hardening**: file handles are opened as streams (lazy read —
  file never fully loaded into memory) and are guaranteed closed on success,
  validation failure, transport exception, and response decode exception via
  `try/finally`. Filenames sanitized against CR/LF, quotes, backslash, empty,
  and unsafe control characters while preserving safe Unicode. Files are validated
  as regular, readable, non-empty before opening.
- **`UserModule` LRU fix**: cache hits now update recency order (previously a hit
  left the entry at its old position, so frequently-read users could be evicted).
- **`WebhookModule::handle()`** documentation corrected: it is a callback-based
  handler, not a PSR-7/PSR-15 middleware.
- **Webhook event DTO**: `WebhookModule::parseEvent()` returns a plain array
  (backward compatible); the new `WebhookModule::parseEventDto()` returns a typed
  `WebhookEvent` value object with `readonly` properties, convenience predicates
  (`isText`, `isImage`, `isSticker`, `isVoice`, `isFollow`, `isUnfollow`),
  `toArray()`, and `ArrayAccess` for the legacy keys — writes and unsets throw.

### Added (tooling and tests)
- `WebhookEvent` DTO with typed readonly properties and `ArrayAccess` compatibility.
  `WebhookModule::parseEventDto()` returns it; `parseEvent()` still returns a plain
  array for backward compatibility.
- `ZaloClient::download(string $url): ResponseInterface` low-level transport
  method for media downloads (redirects are not followed).
- PHPStan (level 6) and PHP-CS-Fixer (PSR-12) configuration with composer scripts.
- CI matrix now includes PHP 8.4. CI runs `composer validate --strict`, tests,
  PHPStan, and php-cs-fixer check with no fallback/skip.
- `composer check` script runs the full quality gate locally.
- `scripts/consumer-smoke.sh` — creates a temp consumer project via a path
  repository, installs dependencies, autoloads, and constructs `ZaloBot` with a
  custom mock PSR-18 client; no network calls.
- Examples: `error-handling.php`, `media-download.php`, `long-polling.php`.
- Test suite grew from 48 to 142 tests with a PSR-18 `MockHttpClient`
  (no real API calls in tests).

### Dependencies
- `psr/http-client` (^1.0) and `psr/http-message` (^2.0) are runtime requirements;
  `guzzlehttp/guzzle` (^7.0 || ^8.0) is kept as a runtime requirement because
  `ZaloBot`'s default HTTP client is Guzzle — consumers can inject any other
  PSR-18 client instead.
- Dev: added `phpstan/phpstan` and `friendsofphp/php-cs-fixer`.
