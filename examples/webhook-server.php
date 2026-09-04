<?php

declare(strict_types=1);

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

/**
 * Example: Webhook server for Zalo Bot PHP SDK
 *
 * Run:
 *   php examples/webhook-server.php
 *
 * Endpoint: POST http://localhost:8080/webhook
 *
 * Set this URL as your Zalo Bot webhook endpoint:
 *   https://your-domain.com/webhook
 */

require __DIR__ . '/../vendor/autoload.php';

use ZaloBot\Sdk\ZaloBot;
use ZaloBot\Sdk\Exceptions\WebhookException;

// ─────────────────────────────────────────────
// Load environment variables (inline loader, no framework needed)
// ─────────────────────────────────────────────

if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

$bot = ZaloBot::fromEnv();

// ─────────────────────────────────────────────
// Router: dispatch to handlers
// ─────────────────────────────────────────────

$requestUri   = $_SERVER['REQUEST_URI']   ?? '/';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Health check
if ($requestMethod === 'GET' && $requestUri === '/health') {
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'timestamp' => date('c'),
    ]);
    exit;
}

// Webhook endpoint
if ($requestMethod === 'POST' && $requestUri === '/webhook') {
    handleWebhook($bot);
    exit;
}

// 404
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['message' => 'Not found']);

// ─────────────────────────────────────────────
// Webhook handler
// ─────────────────────────────────────────────

function handleWebhook(ZaloBot $bot): void
{
    $headers = getallheaders();
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];

    // 1. Verify secret token
    if (!$bot->webhook->verify($headers)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Unauthorized']);
        return;
    }

    // 2. Parse event
    try {
        $event = $bot->webhook->parseEvent($body);
    } catch (WebhookException $e) {
        http_response_code($e->getCode() ?: 400);
        header('Content-Type: application/json');
        echo json_encode(['message' => $e->getMessage()]);
        return;
    }

    // 3. Acknowledge immediately to respect the 5-second window
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'OK']);
    flush();

    // 4. Process event (log, queue, or handle)
    $userId   = $event['userId']   ?? 'unknown';
    $chatId   = $event['chatId']   ?? $userId;
    $eventTag = $event['event']    ?? 'unknown';
    error_log("[ZaloBot] Event: {$eventTag} from {$userId}");

    switch ($eventTag) {
        case 'user_text':
            handleTextMessage($bot, $chatId, $event['message']['text'] ?? '');
            break;

        case 'user_follow':
            $bot->message->sendText(
                $chatId,
                "Thanks for following! 🎉\nSend any message and I will reply!"
            );
            break;

        case 'user_unfollow':
            error_log("[ZaloBot] User {$userId} unfollowed");
            break;

        case 'user_image':
            error_log("[ZaloBot] Image received from {$userId}");
            break;

        case 'user_voice':
            error_log("[ZaloBot] Voice from {$userId}: " . ($event['message']['voiceUrl'] ?? 'none'));
            break;

        default:
            error_log("[ZaloBot] Unhandled event: {$eventTag}");
    }
}

/**
 * Simple echo bot logic for text messages.
 */
function handleTextMessage(ZaloBot $bot, string $chatId, string $text): void
{
    $lower = strtolower(trim($text));

    if ($lower === 'hi' || $lower === 'hello') {
        $bot->message->sendText($chatId, 'Hi! How can I help you?');
        return;
    }

    if (str_contains($lower, 'help')) {
        $bot->message->sendText($chatId, 'I can help you with: Info, Support, Contact');
        return;
    }

    // Default: echo back
    $bot->message->sendText($chatId, "You sent: \"{$text}\"");
}
