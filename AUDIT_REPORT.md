# ZaloBot PHP SDK — Production Audit Report
# Date: 2026-09-05

## P0 — Critical Issues

### 1. ZaloClient.php: Throwable→TimeoutException (CRITICAL)
- Line 128-138: `catch (\Throwable $e)` turns ALL unknown exceptions into TimeoutException
- Fixes: proper exception mapping per status code; keep previous exception chain
- Error mapping: 401/403→AuthException, 429→RateLimitException, 502/503/504→retry, network→NetworkException, timeout→TimeoutException

### 2. ZaloClient.php: Guzzle client recreated per request (CRITICAL)
- Line 57-64: `new Client([...])` inside `request()` method — no DI, no testability
- Fixes: accept PSR-18 HTTP client interface via constructor injection

### 3. ZaloClient.php: upload() creates new Client too (CRITICAL)
- Line 160-163: `new Client([...])` in upload method — same problem
- Fixes: use injected HTTP client for all requests

### 4. MediaModule.php: downloadMedia uses raw cURL (CRITICAL)
- Line 96-113: curl_init/curl_exec without SSRF check on redirect chain
- CURLOPT_FOLLOWLOCATION=true means redirects bypass validateDownloadUrl()
- No HTTP status code check — curl_exec() can return true even on 4xx/5xx
- Fixes: validate each redirect target; check HTTP status; use PSR-18

### 5. MediaModule.php: SSRF protection incomplete
- Only checks hostname regex patterns — misses:
  - IPv6-mapped IPv4: ::ffff:127.0.0.1
  - Decimal IP: 2130706433 (127.0.0.1)
  - URL-encoded: http://127.0.0.1
  - DNS rebinding (hostname resolves to private IP)
- Fixes: resolve DNS before connecting; check ALL resolved IPs

### 6. Retry logic: only retries on 429 (INSUFFICIENT)
- Line 95-98: only `ClientException` with status 429 is retried
- Should also retry: 408, 502, 503, 504, network transient
- Should NOT retry: 400, 401, 403, 404, 422
- Fixes: implement Retry-After header support; exponential backoff + jitter

### 7. UserModule.php: cache hit doesn't update LRU order
- Line 88-89: cache hit returns data without updating cacheOrder
- Fixes: move accessed key to end of cacheOrder on hit

## P1 — Important Issues

### 8. No PSR-18 HTTP client interface — Guzzle locked in
- composer.json requires guzzlehttp/guzzle directly
- Fixes: require psr/http-client + psr/http-message; accept any PSR-18 client

### 9. WebhookModule: handle() documentation claims PSR-7/PSR-15
- Line 137 comment says "PSR-7 / PSR-15 Middleware handler" but it's not
- Fixes: document as callback-based handler only

### 10. No static analysis or coding standard tooling
- No PHPStan config
- No PHP-CS-Fixer / PHPCS
- Fixes: add phpstan.neon, .php-cs-fixer.php

### 11. CI matrix only tests PHP 8.1/8.2/8.3
- Missing PHP 8.4
- Fixes: add PHP 8.4 to matrix

### 12. Missing value objects / DTOs
- WebhookModule::parseEvent returns array — no type safety
- Fixes: add WebhookEvent DTO

### 13. Exceptions: __construct param order inconsistent
- Some have $previous before $details, some after
- Fixes: standardize all exception constructors

## P2 — Nice to Have

### 14. Missing error-handling example
### 15. Missing media download example
### 16. Missing long-polling example
### 17. README needs CI badges
### 18. CHANGELOG needs update
### 19. Version tag strategy (v0.x → v1.0.0)
