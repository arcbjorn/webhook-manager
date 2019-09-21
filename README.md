# Webhook Manager

Reliable webhook delivery system with automatic retries, monitoring, and event logging.

## Features

- Automatic retry with exponential backoff
- Signature verification (HMAC)
- Event filtering
- Delivery history and logs
- Failed delivery alerts
- Batch webhook dispatch
- Rate limiting per endpoint
- Dashboard UI

## Usage

```php
// Register webhook
Webhook::register('user.created', 'https://api.example.com/webhooks');

// Dispatch event
Webhook::dispatch('user.created', [
    'user_id' => 123,
    'email' => 'user@example.com'
]);

// With retry configuration
Webhook::dispatch('order.completed', $data)
    ->retries(5)
    ->backoff([60, 300, 900]); // 1min, 5min, 15min
```

## CLI

```bash
# Test webhook
php artisan webhook:test https://api.example.com/hook --event=test

# Retry failed
php artisan webhook:retry --failed

# Monitor deliveries
php artisan webhook:monitor --tail
```

## Security

- HMAC-SHA256 signatures
- IP whitelist support
- SSL/TLS verification
- Request timeout limits

## Requirements

- PHP 7.2+
- Laravel 6.0
- Queue worker
