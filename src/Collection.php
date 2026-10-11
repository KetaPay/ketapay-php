<?php

declare(strict_types=1);

namespace KetaPay;

/**
 * A list that is not paginated (`object: list`). Iterate it, count it, or read `->data`.
 *
 * @template T of KetaPayObject
 *
 * @property-read 'list' $object
 * @property-read list<T> $data
 *
 * @implements \IteratorAggregate<int, T>
 */
final class Collection extends KetaPayObject implements \IteratorAggregate, \Countable
{
    /**
     * @template I of KetaPayObject
     *
     * @param class-string<I> $itemClass
     *
     * @return self<I>
     */
    public static function of(\stdClass $data, string $itemClass, ?ApiResponse $response = null): self
    {
        /** @var self<I> $list */
        $list = new self();
        $list->fill($data, ['data' => $itemClass]);
        $list->setLastResponse($response);

        return $list;
    }

    /** @return \ArrayIterator<int, T> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items());
    }

    public function count(): int
    {
        return count($this->items());
    }

    /** @return list<T> */
    private function items(): array
    {
        $data = $this->data;

        return is_array($data) ? $data : [];
    }
}
