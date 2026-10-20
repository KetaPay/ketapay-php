<?php

declare(strict_types=1);

namespace KetaPay;

use KetaPay\Models\ObjectTypes;

/**
 * An object from the API. Read fields exactly as the API sends them: `$escrow->funded_at`, or
 * `$escrow['funded_at']`. Objects are read only. `toArray()` gives plain arrays back.
 *
 * Unknown fields (added to the API after this version) are readable too.
 *
 * @implements \ArrayAccess<string, mixed>
 */
class KetaPayObject implements \ArrayAccess, \JsonSerializable
{
    /**
     * Field name => class of the nested object (or of each item, for lists). Generated models fill it in;
     * other nested objects are typed by their `object` field. A field that can hold one of several
     * models lists them all, and the one whose MATCH fits the data is used.
     *
     * @var array<string, class-string<KetaPayObject>|list<class-string<KetaPayObject>>>
     */
    protected const NESTED = [];

    /**
     * Field values that tell this model apart from the others a field may hold (e.g. `type` =>
     * `business`). A model with none is the one used when no other matches.
     *
     * @var array<string, string>
     */
    protected const MATCH = [];

    /** @var array<string, mixed> */
    private array $values = [];

    private ?ApiResponse $lastResponse = null;

    final public function __construct() {}

    /**
     * @param array<string, mixed>|\stdClass $data
     */
    public static function constructFrom(array|\stdClass $data, ?ApiResponse $response = null): static
    {
        if (is_array($data)) {
            // Plain arrays (from your code or a cache) become the same objects a response would.
            $decoded = json_decode(json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), false, 512, JSON_THROW_ON_ERROR);
            $data = $decoded instanceof \stdClass ? $decoded : new \stdClass();
        }
        $object = new static();
        $object->fill($data, static::NESTED);
        $object->lastResponse = $response;

        return $object;
    }

    protected function setLastResponse(?ApiResponse $response): void
    {
        $this->lastResponse = $response;
    }

    /** The raw response this object came from, with its headers and request id. Null for nested objects. */
    public function getLastResponse(): ?ApiResponse
    {
        return $this->lastResponse;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_map(static fn(mixed $value): mixed => self::plain($value), $this->values);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function __set(string $name, mixed $value): never
    {
        throw new \LogicException('KetaPay objects are read only');
    }

    public function __unset(string $name): never
    {
        throw new \LogicException('KetaPay objects are read only');
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->values[(string) $offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->values[(string) $offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new \LogicException('KetaPay objects are read only');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new \LogicException('KetaPay objects are read only');
    }

    /**
     * @param array<string, class-string<KetaPayObject>|list<class-string<KetaPayObject>>> $nested
     */
    protected function fill(\stdClass $data, array $nested): void
    {
        foreach (get_object_vars($data) as $key => $value) {
            $this->values[(string) $key] = self::hydrate($value, $nested[$key] ?? null);
        }
    }

    /**
     * @param class-string<KetaPayObject>|list<class-string<KetaPayObject>>|null $class
     */
    private static function hydrate(mixed $value, string|array|null $class): mixed
    {
        if ($value instanceof \stdClass) {
            if (is_array($class)) {
                $class = self::matching($value, $class);
            }
            if ($class === null) {
                $type = isset($value->object) && is_string($value->object) ? $value->object : '';
                $class = ObjectTypes::MAP[$type] ?? self::class;
            }

            return $class::constructFrom($value);
        }
        if (is_array($value)) {
            return array_map(static fn(mixed $item): mixed => self::hydrate($item, $class), $value);
        }

        return $value;
    }

    /**
     * The model among several whose MATCH fits the data, else the one without a MATCH, else none.
     *
     * @param list<class-string<KetaPayObject>> $classes
     *
     * @return class-string<KetaPayObject>|null
     */
    private static function matching(\stdClass $value, array $classes): ?string
    {
        $fallback = null;
        foreach ($classes as $class) {
            if ($class::MATCH === []) {
                $fallback ??= $class;

                continue;
            }
            $fits = true;
            foreach ($class::MATCH as $field => $expected) {
                $fits = $fits && ($value->{$field} ?? null) === $expected;
            }
            if ($fits) {
                return $class;
            }
        }

        return $fallback;
    }

    private static function plain(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->toArray();
        }
        if (is_array($value)) {
            return array_map(static fn(mixed $item): mixed => self::plain($item), $value);
        }

        return $value;
    }
}
