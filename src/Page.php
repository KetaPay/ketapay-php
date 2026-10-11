<?php

declare(strict_types=1);

namespace KetaPay;

/**
 * One page of a paginated list. `->data` is this page only. Iterating the page walks every item
 * across all pages, fetching the next page by `next_cursor` as needed, with your filters kept:
 *
 *     foreach ($ketapay->escrows->list(['status' => 'active']) as $escrow) { ... }
 *
 * @template T of KetaPayObject
 *
 * @property-read 'list' $object
 * @property-read list<T> $data
 * @property-read bool $has_more
 * @property-read string|null $next_cursor
 *
 * @implements \IteratorAggregate<int, T>
 */
final class Page extends KetaPayObject implements \IteratorAggregate
{
    /** @var \Closure(string): self<T> */
    private \Closure $fetchNext;

    /**
     * @template I of KetaPayObject
     *
     * @param class-string<I>              $itemClass
     * @param \Closure(string): self<I>    $fetchNext fetches the page after a cursor
     *
     * @return self<I>
     */
    public static function of(\stdClass $data, string $itemClass, \Closure $fetchNext, ?ApiResponse $response = null): self
    {
        /** @var self<I> $page */
        $page = new self();
        $page->fill($data, ['data' => $itemClass]);
        $page->setLastResponse($response);
        $page->fetchNext = $fetchNext;

        return $page;
    }

    /** @return \Generator<int, T> */
    public function getIterator(): \Generator
    {
        $page = $this;
        $index = 0;
        while (true) {
            foreach (is_array($page->data) ? $page->data : [] as $item) {
                yield $index++ => $item;
            }
            $cursor = $page->next_cursor;
            if ($page->has_more !== true || !is_string($cursor) || $cursor === '') {
                return;
            }
            $page = ($this->fetchNext)($cursor);
        }
    }
}
