<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Collection;
use KetaPay\Http\HttpClient;
use KetaPay\KetaPayObject;
use KetaPay\Page;

/**
 * Base class for every API resource. Internal.
 *
 * @internal
 */
abstract class AbstractResource
{
    public function __construct(protected readonly HttpClient $http) {}

    /**
     * @template T of KetaPayObject
     *
     * @param class-string<T>           $class
     * @param 'GET'|'POST'|'DELETE'     $method
     * @param array<string, mixed>|null $body
     * @param array<string, mixed>      $options
     *
     * @return T
     */
    protected function object(string $class, string $method, string $path, ?array $body = null, array $options = []): KetaPayObject
    {
        [$data, $response] = $this->http->request($method, $path, body: $body, options: $options);

        return $class::constructFrom($data, $response);
    }

    /**
     * @template T of KetaPayObject
     *
     * @param class-string<T>      $itemClass
     * @param array<string, mixed> $options
     *
     * @return Collection<T>
     */
    protected function collection(string $itemClass, string $path, array $options = []): Collection
    {
        [$data, $response] = $this->http->request('GET', $path, options: $options);

        return Collection::of($data, $itemClass, $response);
    }

    /**
     * @template T of KetaPayObject
     *
     * @param class-string<T>            $itemClass
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>       $options
     *
     * @return Page<T>
     */
    protected function page(string $itemClass, string $path, array $query, array $options): Page
    {
        $fetch = function (?string $cursor) use (&$fetch, $itemClass, $path, $query, $options): Page {
            $params = $cursor === null ? $query : [...$query, 'cursor' => $cursor];
            [$data, $response] = $this->http->request('GET', $path, query: $params, options: $options);

            return Page::of($data, $itemClass, $fetch, $response);
        };

        return $fetch(null);
    }

    /** Encodes one path segment, so an id can never change the route. */
    protected static function seg(string $id): string
    {
        if ($id === '') {
            throw new \InvalidArgumentException('KetaPay: an id is required');
        }

        return rawurlencode($id);
    }

    /**
     * Refuses a call that needs the customer's proof before anything is sent.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    protected static function customer(array $options): array
    {
        if (!isset($options['customerToken']) || !is_string($options['customerToken']) || $options['customerToken'] === '') {
            throw new \InvalidArgumentException("KetaPay: this call needs your customer's proof. Pass ['customerToken' => \$token] from customerSessions->verify().");
        }

        return $options;
    }
}
