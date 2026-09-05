<?php

/**
 * @author  Hoang Khac Phuc
 * @email   hoangkhacphuc.dev@gmail.com
 * @github  https://github.com/hoangkhacphuc
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ZaloBot\Sdk\ZaloBot;

$bot = ZaloBot::fromEnv();
// List available message types
$methods = ['text', 'photo', 'sticker', 'voice'];
echo 'Available message types: ' . implode(', ', $methods) . PHP_EOL;

// Long polling for updates (mutually exclusive with webhook)
try {
    while (true) {
        $updates = $bot->message->getUpdates(30);
        echo "Got " . count($updates['result'] ?? []) . " updates\n";
        sleep(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
}
