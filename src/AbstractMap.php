<?php

/*
 * A typed map of values, each looked up by a key (an int or a string), that can be changed after it's created. Prefer
 * AbstractReadonlyMap unless the map genuinely needs to change, e.g. a cache that a long-running process adds to.
 *
 * A map holds entries of its own entry class (see AbstractEntry), each a key and a value, so PHP checks that every
 * value is given a key, and is of the right type, on the line that creates its entry. Reading the map works with keys
 * and values: get() and $map['key'] give the value, and foreach gives each key and value.
 *
 * It's similar to ramsey/collection's typed maps, so it'll feel familiar, but it isn't compatible with them. It
 * supports array syntax, defensively: $map['key'] = $value creates an entry of the map's entry class (so PHP checks the
 * value's type), $map[] = $entry adds a whole entry, and reading or unsetting a key that isn't there throws, rather
 * than giving null (isset() and ?? still work, as they check first).
 *
 * To create one, extend it with a class whose constructor takes just the entries, as a typed variadic of its entry
 * class, and passes them on, e.g.:
 *
 *     final class InvoiceMap extends AbstractMap
 *     {
 *         public function __construct(InvoiceEntry ...$entries)
 *         {
 *             parent::__construct(...$entries);
 *         }
 *     }
 */

namespace Programster\Collections;

use Programster\Collections\Exceptions\InvalidArgumentException;
use Programster\Collections\Exceptions\NoSuchElementException;
use Programster\Collections\Internal\MapTools;

/**
 * @template TValue
 * @template TEntry of AbstractEntry<TValue>
 * @implements \IteratorAggregate<int|string, TValue>
 * @implements \ArrayAccess<int|string, TValue>
 */
abstract class AbstractMap implements \Countable, \IteratorAggregate, \ArrayAccess, \JsonSerializable
{
    use MapTools;

    # The entries, by their keys.
    /** @var array<array-key, TEntry> */
    protected array $m_entries = [];


    /**
     * Create the map from its entries. This is protected so that every map has to declare its own constructor,
     * taking its entries as a typed variadic of its entry class, so PHP checks their type where the map is made.
     *
     * @param TEntry ...$entries - the entries, in order. Any keys they're given (e.g. by spreading an array) are
     * ignored, as each entry has its own key.
     *
     * @throws InvalidArgumentException - if two entries have the same key, or an entry isn't of the map's entry class.
     *
     * @throws \LogicException - if the subclass's constructor takes anything other than one variadic parameter of an
     * entry class of its own (one that extends AbstractEntry), as the entries' type is read from it, and filter()
     * needs to be able to create a map from just its entries.
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
     * Put an entry in the map, replacing the entry already under its key (which keeps its place), if there is one.
     * @param TEntry $entry - the entry, which must be of the map's entry class.
     * @return void
     * @throws InvalidArgumentException - if the entry isn't of the map's entry class.
     */
    public function put(AbstractEntry $entry): void
    {
        $this->storeEntry($entry);
    }


    /**
     * Remove the entry under a key.
     *
     * @param int|string $key - the key.
     *
     * @return void
     *
     * @throws NoSuchElementException - if there's no entry for the key, as removing it can't do anything, so it's
     * likely to be a mistake (check with containsKey() first, if there might not be one).
     */
    public function remove(int|string $key): void
    {
        if (!array_key_exists($key, $this->m_entries))
        {
            $problem = "Can't remove " . self::describeKey($key) . " from a " . static::class;
            throw new NoSuchElementException("{$problem}, as it isn't there.");
        }

        unset($this->m_entries[$key]);
    }


    /**
     * Remove every entry.
     * @return void
     */
    public function clear(): void
    {
        $this->m_entries = [];
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
     * Get a new map with only the entries that a callback accepts. This map is left as it was.
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
     * key is given as it was when its entry was created. It iterates over a copy, so the map can be changed in the
     * loop.
     *
     * @return \Generator<int|string, TValue>
     */
    public function getIterator(): \Generator
    {
        $entries = $this->m_entries;

        foreach ($entries as $entry)
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
            throw new NoSuchElementException(self::describeBadKey($offset));
        }

        return $this->get($offset);
    }


    /**
     * Put a value in the map, for $map['key'] = $value, which creates an entry of the map's entry class for it (so PHP
     * checks the value's type), or a whole entry, for $map[] = $entry.
     *
     * @param mixed $offset - the key, or null for $map[] = $entry.
     *
     * @param mixed $value - the value, or for $map[] = $entry, the entry.
     *
     * @return void
     *
     * @throws InvalidArgumentException - if it's $map[] = $value, with something that isn't an entry of the map's
     * entry class, or the key isn't an int or string.
     *
     * @throws \TypeError - if it's $map['key'] = $value, and the value isn't of the entry's type.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null)
        {
            if (!($value instanceof AbstractEntry))
            {
                throw new InvalidArgumentException(
                    "\$map[] = ... adds a whole entry to a " . static::class . ", as it has its own key, but was given "
                    . get_debug_type($value) . ". Use \$map['key'] = \$value to put a value under a key."
                );
            }

            $this->storeEntry($value);
        }
        elseif (is_int($offset) || is_string($offset))
        {
            $this->storeEntry($this->createEntry($offset, $value));
        }
        else
        {
            throw new InvalidArgumentException(self::describeBadKey($offset));
        }
    }


    /**
     * Remove the entry under a key, for unset($map['key']).
     * @param mixed $offset - the key.
     * @return void
     * @throws NoSuchElementException - if there's no entry for the key, or the key isn't an int or string.
     */
    public function offsetUnset(mixed $offset): void
    {
        if (!is_int($offset) && !is_string($offset))
        {
            throw new NoSuchElementException(self::describeBadKey($offset));
        }

        $this->remove($offset);
    }


    /**
     * Check an entry is of the map's entry class, and keep it under its key, replacing the entry already there.
     * @param mixed $entry - the entry.
     * @return void
     * @throws InvalidArgumentException - if it isn't an entry of the map's entry class.
     */
    private function storeEntry(mixed $entry): void
    {
        // It's checked to be of the map's entry class, which is TEntry.
        /** @var TEntry $checked */
        $checked = $this->assertOwnEntry($entry);
        $this->m_entries[$checked->getKey()] = $checked;
    }


    // The message for being given a key that isn't an int or string.
    private static function describeBadKey(mixed $offset): string
    {
        return "A " . static::class . "'s keys are ints or strings, not " . get_debug_type($offset) . ".";
    }
}
