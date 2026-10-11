# KetaPay PHP library

Server-side library for the [KetaPay API](https://docs.ketapay.com): put escrow inside your product. No dependencies, PHP 8.1 or later. Works in Laravel, Symfony, WordPress and plain PHP.

> Beta. The API is in design partner testing and may still change.

## Install

```sh
composer require ketapay/ketapay-php:@beta   # the beta
```

No Composer (for example inside a WordPress plugin)? Copy this package into your plugin and `require_once __DIR__ . '/ketapay-php/init.php';`.

## Quick start

```php
use KetaPay\KetaPay;

$ketapay = new KetaPay(getenv('KETAPAY_SECRET_KEY')); // sk_test_... or sk_live_...

$session = $ketapay->checkoutSessions->create([
    'amount' => 45_000_000, // kobo: ₦450,000
    'category' => 'goods',
    'title' => 'iPhone 15 Pro Max 256GB',
    'description' => 'Used, 91% battery, no scratches, Face ID works. Comes with box and cable.',
    'images' => [['url' => 'https://cdn.example.com/listings/8812/1.jpg']],
    'buyer' => ['email' => 'ada@example.com', 'phone' => '+2348012345678'],
    'seller' => ['email' => 'tunde@example.com'],
    'release' => ['method' => 'release_code'],
    'external_reference' => 'order_7781',
]);

// Send the buyer to $session->url, or open it with @ketapay/checkout.
```

Use your secret key on your server only. Publishable keys (`pk_`) are rejected here.

Fields read exactly as the API sends them: `$session->url`, `$escrow->funded_at`, `$escrow->milestones[0]->status`. Objects are read only; `->toArray()` gives a plain array. Status values have constants in `KetaPay\Enums` (`EscrowStatus::ACTIVE`).

## Milestones

```php
$ketapay->checkoutSessions->create([
    'amount' => 150_000_000,
    'category' => 'services',
    'title' => 'Company website',
    'description' => 'Five-page marketing site with blog. Brief attached.',
    'milestones' => [
        ['title' => 'Design', 'amount' => 40_000_000],
        ['title' => 'Build', 'amount' => 80_000_000],
        ['title' => 'Launch', 'amount' => 30_000_000],
    ],
    'buyer' => ['email' => 'ada@example.com'],
    'seller' => ['email' => 'tunde@example.com'],
    'release' => ['method' => 'buyer_confirms', 'auto_release_hours' => 168],
    'commission' => ['rate_bps' => 1000], // your 10% cut, from the seller's side of each release
]);
```

## Acting for your customer

Moving money (delivering, releasing, cancelling, disputes, payout accounts) needs proof from your customer. KetaPay sends them a one-time code; you exchange it for a 15-minute customer token:

```php
$otp = $ketapay->customerSessions->create(['email' => 'ada@example.com']);
$token = $ketapay->customerSessions->verify($otp->id, $codeTheyTyped)->token;

$ketapay->escrows->milestones->release($escrowId, $milestoneId, [], ['customerToken' => $token]);
```

Calls that need the token refuse to run without it, before anything is sent.

## Lists

`list()` returns the first page. `foreach` over it walks every item across all pages:

```php
foreach ($ketapay->escrows->list(['status' => 'active']) as $escrow) {
    echo $escrow->id, ' ', $escrow->status, "\n";
}

$page = $ketapay->escrows->list(['limit' => 10]); // $page->data, $page->has_more, $page->next_cursor
```

## Webhooks

Verify against the raw body, before any JSON parsing:

```php
use KetaPay\Errors\WebhookSignatureError;
use KetaPay\Webhook;

try {
    $event = Webhook::verify(
        file_get_contents('php://input'),
        $_SERVER['HTTP_KETAPAY_SIGNATURE'] ?? null,
        getenv('KETAPAY_WEBHOOK_SECRET'),
    );
} catch (WebhookSignatureError $e) {
    http_response_code(400);
    exit;
}

if ($event->type === 'escrow.funded') {
    // mark the order paid: $event->data->object is the Escrow
}
http_response_code(200);
```

While rotating a secret, pass both: `Webhook::verify($body, $header, [$newSecret, $oldSecret])`. In Laravel, pass `$request->getContent()` and `$request->header('KetaPay-Signature')`. `$ketapay->webhooks->verify()` does the same from the client.

## Errors and retries

Every error extends `KetaPay\Errors\KetaPayError` with `type`, `code`, `param`, `statusCode` and `requestId`. Branch on `code`:

```php
use KetaPay\Errors\InvalidRequestError;

try {
    $ketapay->escrows->milestones->release($escrowId, $milestoneId, [], ['customerToken' => $token]);
} catch (InvalidRequestError $e) {
    if ($e->code === 'dispute_open') {
        // show "this milestone is in dispute"
    }
}
```

`NotFoundError` (404) and `ConflictError` (409, for example `dispute_open`) are subclasses of `InvalidRequestError`.

Network errors, `429` and `5xx` are retried twice with backoff, reusing the same `Idempotency-Key`, so a retried request never runs twice. Change with `new KetaPay(['apiKey' => $key, 'maxRetries' => 3, 'timeoutMs' => 10_000])`, or per call: `['maxRetries' => 0]`. Unknown options (for example `customer_token` instead of `customerToken`) throw, so a typo never goes out silently.

## Request ids, headers and cancelling

```php
// The raw response and request id (quote it to KetaPay support)
$escrow = $ketapay->escrows->retrieve($id);
$escrow->getLastResponse()->requestId;
$escrow->getLastResponse()->header('X-RateLimit-Remaining');

// Cancel a call (and its retries), for example on shutdown
$token = new KetaPay\CancellationToken();
pcntl_signal(SIGTERM, fn () => $token->cancel());
$ketapay->escrows->list([], ['cancel' => $token]);

// Extra headers for every call, or one call
new KetaPay(['apiKey' => $key, 'defaultHeaders' => ['X-Trace-Id' => $traceId]]);
$ketapay->escrows->retrieve($id, ['headers' => ['X-Trace-Id' => $traceId]]);
```

## Logging

Retries are logged at `warn` (the default), to PHP's `error_log`. `'logLevel' => 'debug'` logs every request and response: method, path, status, request id and timing, never keys, customer tokens or bodies. Send logs to any PSR-3 logger (Monolog, Laravel's `Log`) or a callable:

```php
new KetaPay(['apiKey' => $key, 'logLevel' => 'debug', 'logger' => $monolog]);
```

## Sandbox

With an `sk_test_` key, no messages are sent, every one-time code is `123456`, and you can move escrows through any state:

```php
$ketapay->testHelpers->checkoutSessions->pay($session->id);
$ketapay->testHelpers->escrows->simulate($escrowId, ['action' => 'deliver', 'milestone_id' => $milestoneId]);
```

## Frameworks and hosting

**Laravel.** Add the key to `config/services.php` (`'ketapay' => ['secret' => env('KETAPAY_SECRET_KEY')]`) and bind one client in a service provider:

```php
$this->app->singleton(KetaPay::class, fn () => new KetaPay([
    'apiKey' => config('services.ketapay.secret'),
    'logger' => $this->app->make('log'),
]));
```

Then type-hint `KetaPay` in controllers and jobs. Exclude your webhook route from CSRF protection.

**WordPress.** Store the secret key in `wp-config.php` (`define('KETAPAY_SECRET_KEY', 'sk_live_...')`), not in the database. The library has no dependencies, so bundling it can't clash with other plugins. To send through WordPress's HTTP API instead of cURL, implement `KetaPay\Http\Transport` with `wp_remote_request()` and pass it as `'transport'`.

**Your own HTTP client.** cURL is used by default, PHP streams when ext-curl is missing. Any PSR-18 client works too:

```php
new KetaPay(['apiKey' => $key, 'transport' => new KetaPay\Http\Psr18Transport($client, $requestFactory, $streamFactory)]);
```

Building a library on top of this one? Identify it with `'appInfo' => ['name' => 'my-plugin', 'version' => '1.2.0']`: it's added to the User-Agent, so KetaPay support can see which integration made a call.

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md).
