<?php

namespace Arcbjorn\Webhooks;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Queue;

class WebhookManager
{
    protected $client;
    protected $webhooks = [];

    public function __construct()
    {
        $this->client = new Client(['timeout' => 30]);
    }

    public function register($event, $url, array $config = [])
    {
        $this->webhooks[$event][] = [
            'url' => $url,
            'secret' => $config['secret'] ?? null,
            'retries' => $config['retries'] ?? 3,
            'active' => true
        ];
    }

    public function dispatch($event, array $payload)
    {
        if (!isset($this->webhooks[$event])) {
            return;
        }

        foreach ($this->webhooks[$event] as $webhook) {
            if (!$webhook['active']) {
                continue;
            }

            Queue::push(function () use ($webhook, $event, $payload) {
                $this->send($webhook['url'], $event, $payload, $webhook);
            });
        }
    }

    protected function send($url, $event, $payload, $config)
    {
        $data = [
            'event' => $event,
            'payload' => $payload,
            'timestamp' => time()
        ];

        $signature = $this->generateSignature($data, $config['secret'] ?? '');

        try {
            $response = $this->client->post($url, [
                'json' => $data,
                'headers' => [
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $event
                ]
            ]);

            $this->logDelivery($url, $event, $response->getStatusCode(), 'success');

            return true;
        } catch (\Exception $e) {
            $this->logDelivery($url, $event, 0, 'failed', $e->getMessage());

            if ($config['retries'] > 0) {
                $this->retry($url, $event, $payload, $config);
            }

            return false;
        }
    }

    protected function generateSignature(array $data, $secret)
    {
        return hash_hmac('sha256', json_encode($data), $secret);
    }

    protected function retry($url, $event, $payload, $config)
    {
        $config['retries']--;

        $delay = $this->getBackoffDelay($config['retries']);

        Queue::later($delay, function () use ($url, $event, $payload, $config) {
            $this->send($url, $event, $payload, $config);
        });
    }

    protected function getBackoffDelay($retriesLeft)
    {
        $delays = [900, 300, 60]; // 15min, 5min, 1min
        $index = 2 - $retriesLeft;
        return $delays[$index] ?? 60;
    }

    protected function logDelivery($url, $event, $statusCode, $status, $error = null)
    {
        // Log to database or file
        $log = [
            'url' => $url,
            'event' => $event,
            'status_code' => $statusCode,
            'status' => $status,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Implementation depends on logging system
    }

    public function verify($payload, $signature, $secret)
    {
        $expectedSignature = $this->generateSignature($payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }
}
