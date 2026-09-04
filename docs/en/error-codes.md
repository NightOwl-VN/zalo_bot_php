# Error Codes

This document lists all possible error codes returned by the Zalo Bot API and their solutions.

---

## API Error Codes

| Code | Description | Solution |
|------|-------------|----------|
| `0` | Success | — |
| `-1` | Unknown system error | Try again later. Contact Zalo support if persists. |
| `-2` | Invalid parameters | Check request body format and required fields. |
| `-3` | Invalid or expired access token | Renew your access token in Zalo Developer Platform. |
| `-4` | App doesn't have permission for this feature | Check your OA's feature permissions. |
| `-5` | Invalid secret key or signature | Verify your secret key matches the one in Developer Platform. |
| `-6` | Zalo Bot account locked or disabled | Contact Zalo support. |
| `-7` | User blocked the bot or hasn't interacted | Ask the user to follow or interact with the bot first. |
| `-8` | File or media exceeds size limit | Compress files. Max size: 10MB for images, 20MB for files. |
| `-9` | Rate limit exceeded | Slow down requests. Max: 30 requests/minute per access token. |
| `-10` | Unsupported request or endpoint not found | Check the API endpoint URL. |
| `-11` | User not found | Verify the user ID is correct. |
| `-12` | Request timeout | Optimize your server response time. Use async processing. |
| `-13` | Error parsing outgoing content | Check message format (text, JSON structure). |

---

## HTTP Status Codes

| Status | Description | Handling |
|--------|-------------|----------|
| `200` | Success | Proceed with normal flow. |
| `400` | Bad Request | Check request parameters and format. |
| `401` | Unauthorized | Renew access token. |
| `403` | Forbidden | Check OA permissions. |
| `404` | Not Found | Verify endpoint URL. |
| `429` | Too Many Requests | Wait and retry. SDK automatically retries up to 3 times. |
| `500` | Internal Server Error | Try again later. Contact Zalo if persistent. |

---

## Handling Errors in PHP

```php
use ZaloBot\Sdk\Exceptions\ApiException;
use ZaloBot\Sdk\Exceptions\AuthException;
use ZaloBot\Sdk\Exceptions\RateLimitException;
use ZaloBot\Sdk\Exceptions\ValidationException;

try {
    $bot->message->sendText('invalid_user', 'Hello');
} catch (ValidationException $e) {
    error_log('Invalid input: ' . $e->getMessage() . ' (field: ' . ($e->getField() ?? 'unknown') . ')');
} catch (AuthException $e) {
    error_log('Authentication failed: ' . $e->getMessage());
    // Refresh token or prompt user to re-authenticate
} catch (RateLimitException $e) {
    error_log('Rate limited. Retry after: ' . ($e->getRetryAfter() ?? 'unknown') . 's');
    // Wait and retry
} catch (ApiException $e) {
    error_log("API Error {$e->getApiErrorCode()}: {$e->getMessage()}");
    // Handle specific error code
} catch (\Throwable $e) {
    error_log('Unexpected error: ' . $e->getMessage());
}
```

---

## Common Error Scenarios

### 1. Rate Limit Exceeded (`-9`)

**Cause:** Sending too many requests in a short time.

**Solution:**
- Use the SDK's built-in retry logic (enabled by default, exponential backoff)
- Add delays between requests
- Batch messages when possible

```php
// Increase retry attempts via Config
$bot = new ZaloBot(Config::fromEnv([
    'ZALO_BOT_MAX_RETRIES' => '5',
]));
```

### 2. Invalid Access Token (`-3`)

**Cause:** Token expired or revoked.

**Solution:**
- Generate a new token in Zalo Developer Platform
- Use environment variables to avoid hardcoding
- Use `setBotToken()` for runtime token rotation

```php
// Rotate token at runtime
$bot->setBotToken('your_new_token');
```

### 3. User Not Found (`-11`)

**Cause:** User ID is incorrect or user hasn't interacted with the bot.

**Solution:**
- Verify the user ID from webhook events
- Ensure the user has followed the OA
- Use valid test user IDs from Zalo Developer Platform

### 4. Webhook Signature Invalid (`403`)

**Cause:** Secret key mismatch or body tampered.

**Solution:**
- Verify the secret key in `.env` matches the one in Developer Platform
- Ensure you're passing the raw request body (not a re-serialized version)
- Check that you're reading the `X-Bot-Api-Secret-Token` header correctly

### 5. Validation Error

**Cause:** Invalid input passed to an SDK method.

**Solution:**
```php
use ZaloBot\Sdk\Exceptions\ValidationException;

try {
    $bot->message->sendText('', 'Hello');
} catch (ValidationException $e) {
    error_log("Field '{$e->getField()}' failed: {$e->getMessage()}");
}
```
