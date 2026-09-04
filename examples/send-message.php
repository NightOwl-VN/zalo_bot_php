<?php

declare(strict_types=1);

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

/**
 * Example: Send various message types via Zalo Bot PHP SDK
 * Reference: https://bot.zapps.me/docs/apis/sendMessage/
 *
 * Load environment variables from examples/.env before running:
 *   cp examples/.env.example examples/.env
 *   php examples/send-message.php
 */

require __DIR__ . '/../vendor/autoload.php';

use ZaloBot\Sdk\ZaloBot;
use ZaloBot\Sdk\Exceptions\ZaloBotException;

// ─────────────────────────────────────────────
// 1. Initialize the bot from environment variables
// ─────────────────────────────────────────────

// Optionally load a .env file (uncomment if vlucas/phpdotenv is installed):
// if (file_exists(__DIR__ . '/.env')) {
//     $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//     foreach ($lines as $line) {
//         $line = trim($line);
//         if ($line === '' || str_starts_with($line, '#')) {
//             continue;
//         }
//         [$key, $value] = explode('=', $line, 2);
//         $_ENV[trim($key)] = trim($value);
//     }
// }

try {
    $bot = ZaloBot::fromEnv([
        'ZALO_BOT_TOKEN' => getenv('ZALO_BOT_TOKEN') ?: 'YOUR_BOT_TOKEN',
        'ZALO_BOT_SECRET' => getenv('ZALO_BOT_SECRET') ?: null,
    ]);
} catch (ZaloBotException $e) {
    fwrite(STDERR, 'Failed to initialize bot: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

// User ID for testing (replace with real ID)
$testUserId = getenv('ZALO_BOT_TEST_USER_ID') ?: 'user123456789';

// ─────────────────────────────────────────────
// 2. Send a text message
// ─────────────────────────────────────────────

function sendText(ZaloBot $bot, string $chatId): void
{
    echo "1. Sending text message..." . PHP_EOL;
    $result = $bot->message->sendText($chatId, 'Hello from Zalo Bot PHP SDK!');
    echo '   OK, message_id: ' . ($result['result']['message_id'] ?? 'n/a') . PHP_EOL;
}

// ─────────────────────────────────────────────
// 3. Send a photo with caption
// ─────────────────────────────────────────────

function sendPhoto(ZaloBot $bot, string $chatId): void
{
    echo "2. Sending photo message..." . PHP_EOL;
    $result = $bot->message->sendPhoto($chatId, 'https://example.com/image.jpg', [
        'caption' => 'Beautiful nature!',
    ]);
    echo '   OK, message_id: ' . ($result['result']['message_id'] ?? 'n/a') . PHP_EOL;
}

// ─────────────────────────────────────────────
// 4. Send a sticker
// ─────────────────────────────────────────────

function sendSticker(ZaloBot $bot, string $chatId): void
{
    echo "3. Sending sticker message..." . PHP_EOL;
    $result = $bot->message->sendSticker($chatId, 'sticker_id_abc123');
    echo '   OK, message_id: ' . ($result['result']['message_id'] ?? 'n/a') . PHP_EOL;
}

// ─────────────────────────────────────────────
// 5. Run all demos
// ─────────────────────────────────────────────

try {
    sendText($bot, $testUserId);
    sendPhoto($bot, $testUserId);
    sendSticker($bot, $testUserId);
    echo 'All demo messages sent successfully!' . PHP_EOL;
} catch (ZaloBotException $e) {
    fwrite(STDERR, 'SDK error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
