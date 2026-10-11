<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Models\Deleted;
use KetaPay\Models\WebhookEndpoint;
use KetaPay\Models\WebhookEndpointWithSecret;
use KetaPay\Models\WebhookSecret;
use KetaPay\Models\WebhookTestResult;
use KetaPay\Page;

/**
 * @phpstan-import-type CreateWebhookEndpointRequest from \KetaPay\Types\Params
 * @phpstan-import-type ListWebhookEndpointsParams from \KetaPay\Types\Params
 * @phpstan-import-type UpdateWebhookEndpointRequest from \KetaPay\Types\Params
 * @phpstan-import-type RequestOptions from \KetaPay\Http\HttpClient
 */
final class WebhookEndpoints extends AbstractResource
{
    /**
     * The response is the only time `secret` is shown. Store it.
     *
     * @param CreateWebhookEndpointRequest $params url, enabled_events, description
     * @param RequestOptions       $options
     */
    public function create(array $params, array $options = []): WebhookEndpointWithSecret
    {
        return $this->object(WebhookEndpointWithSecret::class, 'POST', '/webhook_endpoints', $params, $options);
    }

    /** @param RequestOptions $options */
    public function retrieve(string $id, array $options = []): WebhookEndpoint
    {
        return $this->object(WebhookEndpoint::class, 'GET', '/webhook_endpoints/' . self::seg($id), options: $options);
    }

    /**
     * Change the URL or events, or re-enable a `failing` endpoint with `['status' => 'enabled']`.
     *
     * @param UpdateWebhookEndpointRequest $params
     * @param RequestOptions       $options
     */
    public function update(string $id, array $params, array $options = []): WebhookEndpoint
    {
        return $this->object(WebhookEndpoint::class, 'POST', '/webhook_endpoints/' . self::seg($id), $params, $options);
    }

    /**
     * @param ListWebhookEndpointsParams $params limit, cursor
     * @param RequestOptions             $options
     *
     * @return Page<WebhookEndpoint>
     */
    public function list(array $params = [], array $options = []): Page
    {
        return $this->page(WebhookEndpoint::class, '/webhook_endpoints', $params, $options);
    }

    /** @param RequestOptions $options */
    public function del(string $id, array $options = []): Deleted
    {
        return $this->object(Deleted::class, 'DELETE', '/webhook_endpoints/' . self::seg($id), options: $options);
    }

    /**
     * Sends a signed sample event now and reports what your server answered.
     *
     * @param RequestOptions $options
     */
    public function test(string $id, string $type, array $options = []): WebhookTestResult
    {
        return $this->object(WebhookTestResult::class, 'POST', '/webhook_endpoints/' . self::seg($id) . '/test', ['type' => $type], $options);
    }

    /**
     * New signing secret; the old one keeps signing alongside it for 24 hours.
     *
     * @param RequestOptions $options
     */
    public function rotateSecret(string $id, array $options = []): WebhookSecret
    {
        return $this->object(WebhookSecret::class, 'POST', '/webhook_endpoints/' . self::seg($id) . '/rotate_secret', options: $options);
    }
}
