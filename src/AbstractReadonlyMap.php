<?php

/*
 * A typed map of values, each looked up by a key (an int or a string), that can't be changed once it's created. Methods
 * that would change it (with() and without()) return a new map instead, and leave the original as it was. Use one
 * wherever a lookup table doesn't need to change after it's created.
 *
 * A map holds entries of its own entry class (see AbstractEntry), each a key and a value, so PHP checks that every
 * value is given a key, and is of the right type, on the line that creates its entry. Reading the map works with keys
 * and values: get() and $map['key'] give the value, and foreach gives each key and value.
 *
 * It's similar to ramsey/collection's typed maps, so it'll feel familiar, but it isn't compatible with them. Array
 * syntax can read it ($map['key'], isset() and ??), but writing to it ($map['key'] = $x or unset($map['key'])) throws
 * an UnsupportedOperationException. PHP can't refuse those where they're written, so the package's PHPStan rule
 * (ReadonlyWriteRule) reports them there instead.
 *
 * To create one, extend it with a readonly class whose constructor takes just the entries, as a typed variadic of its
 * entry class, and passes them on, e.g.:
 *
 *     final readonly class InvoiceMap extends AbstractReadonlyMap
 *     {
 *         public function __construct(InvoiceEntry ...$entries)
 *         {
 *             parent::__construct(...$entries);
 *         }
 *     }
 *
 *     $invoices = new InvoiceMap(new InvoiceEntry(12, $invoice), new InvoiceEntry(15, $otherInvoice));
 *     $invoice = $invoices->get(12);
 */

namespace Programster\Collections;

use Programster\Collections\Exceptions\InvalidArgumentException;
use Programster\Collections\Exceptions\NoSuchElementException;
use Programster\Collections\Exceptions\UnsupportedOperationException;
use Programster\Collections\Internal\MapTools;

/**
 * @template TValue
 * @template TEntry of AbstractEntry<TValue>
 * @implements \IteratorAggregate<int|string, TValue>
 * @implements \ArrayAccess<int|string, TValue>
 */
abstract readonly class AbstractReadonlyMap implements \Countable, \IteratorAggregate, \ArrayAccess, \JsonSerializable
{
    use MapTools;

    # The entries, by their keys.
    /** @var array<array-key, TEntry> */
    protected array $m_entries;


    /**
     * Create the map from its entries. This is protected so that every map has to declare its own constructor,
     * taking its entries as a typed variadic of its entry class, so PHP checks their type where the map is made.
     *
     * @param TEntry ...$entries - the entries, in order. Any keys they're given (e.g. by spreading an array) are
     * ignored, as each entry has its own key.
     *
     * @throws InvalidArgumentException - if two entries have the same key, or an entry isn't of the map's entry class
     * (which can only happen if the subclass's constructor passes on something other than what it was given).
     *
     * @throws \LogicException - if the subclass's constructor takes anything other than one variadic parameter of an
     * entry class of its own (one that extends AbstractEntry), as the entries' type is read from it, and the methods
     * that return a new map need to be able to create one from just its entries.
     */
    protected function __construct(mixed ...$entries)
    {
        self::assertValidConstructor(static::class);
        self::assertValidEntryType(static::class);

        /** @var array<array-key, TEntry> $keyed */
        $keyed = $this->keyEntries($entries);
        $this->m_entries = $keyed;
    }


    /**
     * Get the value under a key.
     * @param int|string $key - the key.
     * @return TValue
     * @throws NoSuchElementException - if there isn't one (check with containsKey() first, if there might not be).
     */
    public function get(int|string $key): mixed
    {
        if (!array_key_exists($key, $this->m_entries))
        {
            $problem = "A " . static::class . " has nothing for the key " . self::describeKey($key);
            throw new NoSuchElementException("{$problem}.");
        }

        return $this->m_entries[$key]->getValue();
    }


    /**
     * Check whether the map has a value for a key.
     * @param int|string $key - the key.
     * @return bool
     */
    public function containsKey(int|string $key): bool
    {
        return array_key_exists($key, $this->m_entries);
    }


    /**
     * Check whether the map holds a value.
     *
     * @param mixed $value - the value to look for.
     *
     * @param bool $strict - whether to compare with === (the default), so an object only matches itself, rather than
     * with ==, where an object matches any equal object of the same class.
     *
     * @return bool
     */
    public function contains(mixed $value, bool $strict = true): bool
    {
        return in_array($value, $this->values(), $strict);
    }


    /**
     * Get the keys, in order, each as it was when its entry was created.
     * @return list<int|string>
     */
    public function keys(): array
    {
        return self::readKeys($this->m_entries);
    }


    /**
     * Get the values without their keys, numbered from 0, in order.
     * @return list<TValue>
     */
    public function values(): array
    {
        $values = [];

        foreach ($this->m_entries as $entry)
        {
            $values[] = $entry->getValue();
        }

        return $values;
    }


    /**
     * Get the entries, in order, e.g. to create another map from them.
     * @return list<TEntry>
     */
    public function getEntries(): array
    {
        return array_values($this->m_entries);
    }


    /**
     * Get a new map with an entry added, or replacing the entry already under its key (which keeps its place). This
     * is the way to add an entry, as the map can't be changed.
     *
     * @param TEntry $entry - the entry, which must be of the map's entry class.
     *
     * @return static
     *
     * @throws InvalidArgumentException - if the entry isn't of the map's entry class.
     */
    public function with(AbstractEntry $entry): static
    {
        $this->assertOwnEntry($entry);
        $entries = $this->m_entries;
        $entries[$entry->getKey()] = $entry;

        return new static(...array_values($entries));
    }


    /**
     * Get a new map without the entry under a key.
     *
     * @param int|string $key - the key.
     *
     * @return static
     *
     * @throws NoSuchElementException - if there's no entry for the key, as removing it can't do anything, so it's
     * likely to be a mistake (check with containsKey() first, if there might not be one).
     */
    public function without(int|string $key): static
    {
        if (!array_key_exists($key, $this->m_entries))
        {
            $problem = "Can't remove " . self::describeKey($key) . " from a " . static::class;
            throw new NoSuchElementException("{$problem}, as it isn't there.");
        }

        $entries = $this->m_entries;
        unset($entries[$key]);

        return new static(...array_values($entries));
    }


    /**
     * Get a new map with only the entries that a callback accepts.
     * @param callable(TValue, int|string): bool $callback - given each value and its key, returns true to keep it.
     * @return static
     */
    public function filter(callable $callback): static
    {
        $kept = [];

        foreach ($this->m_entries as $entry)
        {
            if ($callback($entry->getValue(), $entry->getKey()))
            {
                $kept[] = $entry;
            }
        }

        return new static(...$kept);
    }


    /**
     * Apply a callback to every value, and get the results under the same keys. This returns an array rather than a
     * map, as the results can be of any type.
     *
     * @template TResult
     *
     * @param callable(TValue, int|string): TResult $callback - given each value and its key, returns its result.
     *
     * @return array<array-key, TResult>
     */
    public function map(callable $callback): array
    {
        $results = [];

        foreach ($this->m_entries as $key => $entry)
        {
            $results[$key] = $callback($entry->getValue(), $entry->getKey());
        }

        return $results;
    }


    /**
     * Get the values as an array, by key. PHP stores a string key that's a whole number (e.g. "123") as that number,
     * as it does in any array, so use keys() or foreach where a key's type matters.
     *
     * @return array<array-key, TValue>
     */
    public function toArray(): array
    {
        $values = [];

        foreach ($this->m_entries as $key => $entry)
        {
            $values[$key] = $entry->getValue();
        }

        return $values;
    }


    /**
     * Check whether the map has no entries.
     * @return bool
     */
    public function isEmpty(): bool
    {
        return count($this->m_entries) === 0;
    }


    /**
     * Get the number of entries, which also lets count() be used on the map.
     * @return int
     */
    public function count(): int
    {
        return count($this->m_entries);
    }


    /**
     * Get an iterator over the values, by key, which lets the map be used in a foreach ($map as $key => $value). Each
     * key is given as it was when its entry was created.
     *
     * @return \Generator<int|string, TValue>
     */
    public function getIterator(): \Generator
    {
        foreach ($this->m_entries as $entry)
        {
            yield $entry->getKey() => $entry->getValue();
        }
    }


    /**
     * The map as a JSON object of its values, by key. It's always an object, even when it's empty ({}), or its keys
     * are 0, 1, 2 and so on, which json_encode() would otherwise give as an array.
     *
     * @return \stdClass
     */
    public function jsonSerialize(): \stdClass
    {
        return (object)$this->toArray();
    }


    /**
     * Whether there's a value for a key, for isset($map['key']) (and ??). False for a key that isn't an int or string.
     * @param mixed $offset - the key.
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return (is_int($offset) || is_string($offset)) && array_key_exists($offset, $this->m_entries);
    }


    /**
     * The value for a key, for $map['key']. The same as get().
     * @param mixed $offset - the key.
     * @return TValue
     * @throws NoSuchElementException - if there isn't one, or the key isn't an int or string. (isset() and ?? check
     * first.)
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!is_int($offset) && !is_string($offset))
        {
            throw new NoSuchElementException(
                "A " . static::class . "'s keys are ints or strings, not " . get_debug_type($offset) . "."
            );
        }

        return $this->get($offset);
    }


    /**
     * Always throws, as the map can't be changed. It's only here because ArrayAccess needs it, so that $map['key'] can
     * be read. The package's PHPStan rule reports $map['key'] = $x where it's written.
     *
     * @param mixed $offset - the key.
     *
     * @param mixed $value - the value.
     *
     * @return never
     *
     * @throws UnsupportedOperationException - always.
     */
    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new UnsupportedOperationException(
            "A " . static::class . " can't be changed, so it can't be written to with array syntax. Use with() to get "
            . "a new map with the entry."
        );
    }


    /**
     * Always throws, as the map can't be changed. It's only here because ArrayAccess needs it, so that $map['key'] can
     * be read. The package's PHPStan rule reports unset($map['key']) where it's written.
     *
     * @param mixed $offset - the key.
     *
     * @return never
     *
     * @throws UnsupportedOperationException - always.
     */
    public function offsetUnset(mixed $offset): never
    {
        throw new UnsupportedOperationException(
            "A " . static::class . " can't be changed, so an entry can't be unset. Use without() to get a new map "
            . "without it."
        );
    }
}
