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

Nothing yet.
