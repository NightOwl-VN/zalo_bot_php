<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

/**
 * Error handling example. Set ZALO_BOT_TOKEN before running.
 */
require __DIR__ . '/../vendor/autoload.php';

use ZaloBot\Sdk\ZaloBot;
use ZaloBot\Sdk\Exceptions\ApiException;
use ZaloBot\Sdk\Exceptions\AuthException;
use ZaloBot\Sdk\Exceptions\NetworkException;
use ZaloBot\Sdk\Exceptions\RateLimitException;
use ZaloBot\Sdk\Exceptions\TimeoutException;
use ZaloBot\Sdk\Exceptions\ZaloBotException;

$bot = ZaloBot::fromEnv();
try {
    $result = $bot->message->sendText(getenv('ZALO_BOT_CHAT_ID') ?: 'chat-id', 'Hello');
    printf("Sent: %s\n", $result['result']['message_id'] ?? 'unknown');
} catch (AuthException $e) {
    fwrite(STDERR, "Authentication failed; check ZALO_BOT_TOKEN.\n");
} catch (RateLimitException $e) {
    fwrite(STDERR, 'Rate limited; retry after ' . ($e->getRetryAfter() ?? 'an unspecified interval') . " seconds.\n");
} catch (TimeoutException|NetworkException $e) {
    fwrite(STDERR, 'Temporary transport failure: ' . $e->getMessage() . "\n");
} catch (ApiException $e) {
    fwrite(STDERR, "Zalo API error {$e->getApiErrorCode()}: {$e->getMessage()}\n");
} catch (ZaloBotException $e) {
    fwrite(STDERR, 'SDK error: ' . $e->getMessage() . "\n");
}

// Never log bot tokens, secret keys, or full request URLs.
