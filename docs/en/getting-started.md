# Getting Started

This guide will walk you through creating a Zalo bot, setting up the PHP SDK, and deploying it to production.

---

## Step 1: Create a Zalo Bot

1. Go to the [Zalo Bot Platform](https://bot.zapps.me/)
2. Log in with your Zalo account
3. Click **"Create Bot"** (Zalo Bot Platform, not Zalo Official Account)
4. Fill in your bot's name, description, and other required fields
5. After creation, go to the bot detail page
6. Copy your **Bot Token** and **Secret Key**

> ⚠️ **Important:** Save these credentials securely. The Bot Token is used for API authentication, and the Secret Key is required for webhook signature verification.

---

## Step 2: Install the SDK

```bash
composer require hoangkhacphuc/zalobot-sdk
```

---

## Step 3: Configure Environment Variables

Create a `.env` file in your project root:

```env
ZALO_BOT_TOKEN=your_bot_token_here
ZALO_BOT_SECRET=your_secret_key_here
ZALO_BOT_TIMEOUT=30000             # optional
ZALO_BOT_MAX_RETRIES=3             # optional
```

> 💡 Use a `.env` loader like [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv) or [symfony/dotenv](https://symfony.com/doc/current/components/dotenv.html) to automatically load these into `$_ENV`.

---

## Step 4: Write Your First Bot

Create a PHP file (e.g. `index.php`):

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use ZaloBot\Sdk\ZaloBot;

// Initialize bot — reads from .env (load via vlucas/phpdotenv or similar)
$bot = ZaloBot::fromEnv();

// Send a test message
$bot->message->sendText('USER_ID', 'Hello from Zalo Bot PHP SDK!');
```

---

## Step 5: Handle Webhooks (Native PHP)

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use ZaloBot\Sdk\ZaloBot;
use ZaloBot\Sdk\Modules\WebhookModule;

$bot = ZaloBot::fromEnv();

// Read incoming request
$headers = getallheaders();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Verify the secret token
if (!$bot->webhook->verify($headers)) {
    http_response_code(403);
    echo json_encode(['message' => 'Unauthorized']);
    exit;
}

// Parse the event
$event = $bot->webhook->parseEvent($body);

// Handle the event
if ($event['event'] === 'user_text') {
    $bot->message->sendText($event['chatId'], "You said: {$event['message']['text']}");
}

// Always respond 200 OK
http_response_code(200);
echo json_encode(['message' => 'OK']);
```

---

## Step 6: Test Locally with Tunneling

For local testing, expose your webhook endpoint using:

**Using ngrok:**
```bash
ngrok http 8080
# Copy the HTTPS URL and set it in Zalo Developer Platform → Webhook
```

**Using Cloudflare Tunnel:**
```bash
cloudflared tunnel --url http://localhost:8080
```

Set the generated URL as your webhook endpoint in the Zalo Developer Platform.

---

## Step 7: Deploy to Production

### Option A: Deploy on Render

1. Push your code to GitHub
2. Create a new Web Service on Render
3. Set environment variables
4. Deploy

### Option B: Deploy on Railway

1. Push your code to GitHub
2. Create a new project on Railway
3. Add environment variables
4. Deploy

### Option C: Deploy on a VPS

```bash
# Clone repository
git clone https://github.com/NightOwl-VN/zalo_bot_php.git
cd zalo_bot_php

# Install dependencies
composer install --no-dev --optimize-autoloader

# Start with PHP built-in server (development)
php -S 0.0.0.0:8080

# Or deploy with PHP-FPM + Nginx / Apache for production
```

### Option D: Deploy on Laravel / Symfony

The SDK integrates seamlessly with popular PHP frameworks:

**Laravel:**
```php
// In a route or controller
use ZaloBot\Sdk\ZaloBot;

Route::post('/webhook/zalo', function () {
    $bot = ZaloBot::fromEnv();
    $headers = getallheaders();
    $body = json_decode(file_get_contents('php://input'), true);

    if (!$bot->webhook->verify($headers)) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $event = $bot->webhook->parseEvent($body);

    // Process event asynchronously
    dispatch(new ProcessZaloEvent($event));

    return response()->json(['message' => 'OK']);
});
```

Configure Nginx as a reverse proxy (optional).

---

## Next Steps

- Check the [API Reference](./api-reference.md) for all available methods
- Learn about [Webhook Events](./webhook-events.md) to handle user interactions
- Review [Error Codes](./error-codes.md) for troubleshooting
