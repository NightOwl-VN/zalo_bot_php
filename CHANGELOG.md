# Changelog

All notable changes to the **Zalo Bot PHP SDK** project will be documented in this file.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-09-04

### Added
- **Initial Release**: PHP SDK for Zalo Bot Platform (bot.zapps.me), migrated from the Node.js [zalobot-sdk](https://github.com/NightOwl-VN/zalobot-sdk).
- `ZaloBot`: Entry point supporting both configuration array and `Config` object initialization, plus `fromEnv()` for auto-reading environment variables.
- `ZaloClient`: HTTP client built on Guzzle 7 with automatic exponential-backoff retry on rate limits (HTTP 429) and comprehensive API error handling.
- `Config`: Strict configuration management and validation with typed properties, masked token display, and environment variable loading.
- PSR-4 autoloading under the `ZaloBot\Sdk\` namespace.

### Modules
- `MessageModule`: Send text messages (`sendText`), photos (`sendPhoto`), stickers (`sendSticker`), voice messages (`sendVoice`), chat actions (`sendChatAction`). Webhook management (`setWebhook`, `deleteWebhook`, `getWebhookInfo`, `testWebhook`) and bot info (`getMe`).
- `WebhookModule`: Token-based verification via `X-Bot-Api-Secret-Token` header using timing-safe `hash_equals` comparison (`verify`, `requireValid`), event parsing (`parseEvent`), generic handler callback (`handle`). Includes full `EVENT_MAP` for normalized event names.
- `UserModule`: Get user profiles (`getProfile`), list followers (`getFollowers`), check follow status (`isFollowing`). Built-in in-memory cache with 5-minute TTL and LRU eviction (`getProfileCached`, `clearCache`).
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
- GitHub Actions CI workflow with PHP 8.1/8.2/8.3 matrix, Dependabot for Composer, issue/PR templates.

## [Unreleased]

### Changed (BREAKING-safe refactors)
- **`ZaloClient` HTTP stack**: `ZaloClient` now accepts any **PSR-18** `ClientInterface`
  via constructor injection (optional 5th arg, defaults to a Guzzle client for
  backward compatibility). No client is instantiated per request anymore.
- **Exception mapping**: `catch (\Throwable)` no longer converts every unknown error
  into `TimeoutException`. Status codes now map precisely:
  `401/403 → AuthException`, `429 → RateLimitException` (with `Retry-After` support),
  other 4xx/5xx → `ApiException`, transport-level errors → `NetworkException`,
  actual timeouts → `TimeoutException`. Previous exception chain always preserved.
- **Retry policy**: retries on `408`, `429`, `502`, `503`, `504` (previously only 429),
  exponential backoff with jitter, honors `Retry-After` header. `400/401/403/404/422`
  are never retried.
- **`MediaModule::downloadMedia()`** now uses the injected PSR-18 client instead of raw
  cURL with `CURLOPT_FOLLOWLOCATION`; every download validates HTTP status and the
  resolved host before writing to disk. Partial files are cleaned up on failure.
- **SSRF hardening** (MediaModule): blocked IPv4-mapped IPv6 (`::ffff:127.0.0.1`),
  decimal IPs (`2130706433`), hex (`0x7f000001`) and octal (`0177.0.0.1`) forms,
  link-local (fe80::/10), ULA (fc00::/7), `localhost`/`.localhost`, and DNS-resolved
  hostnames that resolve to private addresses (DNS-rebinding defense).
- **`UserModule` LRU fix**: cache hits now update recency order (previously a hit
  left the entry at its old position, so frequently-read users could be evicted).
- **`WebhookModule::handle()`** documentation corrected: it is a callback-based
  handler, not a PSR-7/PSR-15 middleware.

### Added
- `WebhookEvent` DTO with typed readonly properties, convenience predicates
  (`isText`, `isImage`, `isSticker`, `isVoice`, `isFollow`, `isUnfollow`) and
  `ArrayAccess` compatibility. `WebhookModule::parseEventDto()` returns it;
  `parseEvent()` still returns a plain array for backward compatibility.
- `ZaloClient::download(string $url): ResponseInterface` for media downloads.
- PHPStan (level 6) and PHP-CS-Fixer (PSR-12) configuration with composer scripts.
- CI matrix now includes PHP 8.4.
- Examples: `error-handling.php`, `media-download.php`, `long-polling.php`.
- Test suite grew from 48 to 121 tests with a PSR-18 `MockHttpClient`
  (no real API calls in tests).

### Dependencies
- `psr/http-client` (^1.0) is now a runtime requirement; `guzzlehttp/guzzle`
  moved to `require-dev` (any PSR-18 client can be injected).
- Dev: added `phpstan/phpstan` and `friendsofphp/php-cs-fixer`.
