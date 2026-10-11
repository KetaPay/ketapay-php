<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Models\Event;
use KetaPay\Page;

/**
 * The last 30 days of events, for catching up on anything your endpoint missed.
 *
 * @phpstan-import-type RequestOptions from \KetaPay\Http\HttpClient
 */
final class Events extends AbstractResource
{
    /** @param RequestOptions $options */
    public function retrieve(string $id, array $options = []): Event
    {
        return $this->object(Event::class, 'GET', '/events/' . self::seg($id), options: $options);
    }

    /**
     * @param array<string, scalar|null> $params filters: type, limit, cursor
     * @param RequestOptions             $options
     *
     * @return Page<Event>
     */
    public function list(array $params = [], array $options = []): Page
    {
        return $this->page(Event::class, '/events', $params, $options);
    }
}
