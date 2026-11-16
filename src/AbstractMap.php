<?php

/*
 * A typed map, of elements looked up by a string key (e.g. users by their name), that can be changed after it's
 * created. Prefer AbstractReadonlyMap unless the map genuinely needs to change, e.g. one a long-running process keeps
 * up to date.
 *
 * It's similar to ramsey/collection's typed maps, so it'll feel familiar, but it isn't compatible with them. It
 * supports array syntax ($map['key'], $map['key'] = $x, isset() and unset()), but defensively: $map[] = $x is rejected
 * (an element needs a key), reading a key that isn't there throws rather than giving null (isset() and ?? still work,
 * as they check first), and so does unsetting one.
 *
 * To create one, extend it with a class whose constructor takes the elements as a typed variadic, and passes them on,
 * and whose getType() returns the elements' type, e.g.:
 *
 *     final class UserMap extends AbstractMap
 *     {
 *         public function __construct(User ...$users)
 *         {
 *             parent::__construct(...$users);
 *         }
 *
 *         public function getType(): string { return User::class; }
 *     }
 *
 * Then give it the elements by name, by spreading an associative array, or with named arguments, e.g.
 * new UserMap(...['ann' => $ann]), or add them with put().
 */

namespace Programster\Collections;

use Programster\Collections\Exceptions\InvalidArgumentException;
use Programster\Collections\Exceptions\NoSuchElementException;
use Programster\Collections\Internal\MapTools;

/**
 * @template T
 * @implements \IteratorAggregate<string, T>
 * @implements \ArrayAccess<string, T>
 */
abstract class AbstractMap implements \Countable, \IteratorAggregate, \ArrayAccess, \JsonSerializable
{
    use MapTools;

    /** @var array<string, T> */
    protected array $m_elements = [];


    /**
     * Create the map from its elements. This is protected so that every map has to declare its own constructor,
     * taking its elements as a typed variadic, so PHP checks their type where the map is made.
     *
     * @param T ...$elements - the elements, each given by its key (by spreading an associative array, or with named
     * arguments). They're kept in the order given.
     *
     * @throws InvalidArgumentException - if an element has no key (it was passed by position, or its key is a whole
     * number, which PHP turns into a position), or isn't of the map's type.
     *
     * @throws \LogicException - if the subclass's constructor takes anything other than one variadic parameter, as
     * filter() needs to be able to create one from just its elements, or if its getType() doesn't name a type that
     * exists.
     */
    protected function __construct(mixed ...$elements)
    {
        self::assertValidMapConstructor(static::class);
        $this->assertValidType();

        /** @var array<string, T> $checked */
        $checked = $this->checkElements($elements);
        $this->m_elements = $checked;
    }


    /**
     * Put an element under a key, replacing the one already there (which keeps its place), if there is one.
     * @param string $key - the key.
     * @param T $element - the element.
     * @return void
     * @throws InvalidArgumentException - if the element isn't of the map's type.
     */
    public function put(string $key, mixed $element): void
    {
        $this->checkValue($key, $element, $this->getType());
        $this->m_elements[$key] = $element;
    }


    /**
     * Remove the element under a key.
     *
     * @param string $key - the key.
     *
     * @return void
     *
     * @throws NoSuchElementException - if there's no element for the key, as removing it can't do anything, so it's
     * likely to be a mistake (check with containsKey() first, if there might not be one).
     */
    public function remove(string $key): void
    {
        if (!array_key_exists($key, $this->m_elements))
        {
            $problem = "Can't remove \"{$key}\" from a " . static::class;
            throw new NoSuchElementException("{$problem}, as it isn't there.");
        }

        unset($this->m_elements[$key]);
    }


    /**
     * Remove every element.
     * @return void
     */
    public function clear(): void
    {
        $this->m_elements = [];
    }


    /**
     * Get the element with a key.
     * @param string $key - the key.
     * @return T
     * @throws NoSuchElementException - if there isn't one (check with containsKey() first, if there might not be).
     */
    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->m_elements))
        {
            throw new NoSuchElementException("A " . static::class . " has nothing for the key \"{$key}\".");
        }

        return $this->m_elements[$key];
    }


    /**
     * Check whether the map has an element for a key.
     * @param string $key - the key.
     * @return bool
     */
    public function containsKey(string $key): bool
    {
        return array_key_exists($key, $this->m_elements);
    }


    /**
     * Check whether the map holds an element.
     *
     * @param mixed $element - the element to look for.
     *
     * @param bool $strict - whether to compare with === (the default), so an object only matches itself, rather than
     * with ==, where an object matches any equal object of the same class.
     *
     * @return bool
     */
    public function contains(mixed $element, bool $strict = true): bool
    {
        return in_array($element, $this->m_elements, $strict);
    }


    /**
     * Get the keys, in order.
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->m_elements);
    }


    /**
     * Get the elements without their keys, numbered from 0, in order.
     * @return list<T>
     */
    public function values(): array
    {
        return array_values($this->m_elements);
    }


    /**
     * Get a new map with only the elements that a callback accepts. This map is left as it was.
     * @param callable(T, string): bool $callback - given each element and its key, returns true to keep it.
     * @return static
     */
    public function filter(callable $callback): static
    {
        return new static(...array_filter($this->m_elements, $callback, ARRAY_FILTER_USE_BOTH));
    }


    /**
     * Apply a callback to every element, and get the results under the same keys. This returns an array rather than
     * a map, as the results can be of any type.
     *
     * @param callable(T, string): mixed $callback - given each element and its key, returns its result.
     *
     * @return array<string, mixed>
     */
    public function map(callable $callback): array
    {
        $results = [];

        foreach ($this->m_elements as $key => $element)
        {
            $results[$key] = $callback($element, $key);
        }

        return $results;
    }


    /**
     * Get the elements as an array, by key.
     * @return array<string, T>
     */
    public function toArray(): array
    {
        return $this->m_elements;
    }


    /**
     * Check whether the map has no elements.
     * @return bool
     */
    public function isEmpty(): bool
    {
        return count($this->m_elements) === 0;
    }


    /**
     * Get the number of elements, which also lets count() be used on the map.
     * @return int
     */
    public function count(): int
    {
        return count($this->m_elements);
    }


    /**
     * Get an iterator over the elements, by key, which lets the map be used in a foreach ($map as $key => $element).
     * It iterates over a copy, so the map can be changed in the loop.
     *
     * @return \ArrayIterator<string, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->m_elements);
    }


    /**
     * The map as a JSON object, by key. An empty map is an empty object ({}), not an empty array, so it's always the
     * same kind of thing in the JSON.
     *
     * @return array<string, T>|\stdClass
     */
    public function jsonSerialize(): array|\stdClass
    {
        return (count($this->m_elements) === 0) ? new \stdClass() : $this->m_elements;
    }


    /**
     * Whether there's an element for a key, for isset($map['key']) (and ??). False for a key that isn't a string.
     * @param mixed $offset - the key.
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && array_key_exists($offset, $this->m_elements);
    }


    /**
     * The element for a key, for $map['key'].
     * @param mixed $offset - the key.
     * @return T
     * @throws NoSuchElementException - if there isn't one, or the key isn't a string. (isset() and ?? check first.)
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!is_string($offset))
        {
            throw new NoSuchElementException(self::describeNonStringKey($offset));
        }

        return $this->get($offset);
    }


    /**
     * Put an element under a key, for $map['key'] = $element.
     *
     * @param mixed $offset - the key.
     *
     * @param mixed $value - the element.
     *
     * @return void
     *
     * @throws InvalidArgumentException - if there's no key ($map[] = $element), it isn't a string, or the element
     * isn't of the map's type.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_string($offset))
        {
            $given = ($offset === null) ? "none" : get_debug_type($offset);

            throw new InvalidArgumentException(
                "An element of a " . static::class . " needs a string key, e.g. \$map['name'] = \$element, but was "
                . "given {$given}."
            );
        }

        $this->put($offset, $value);
    }


    /**
     * Remove the element under a key, for unset($map['key']).
     * @param mixed $offset - the key.
     * @return void
     * @throws NoSuchElementException - if there's no element for the key, or the key isn't a string.
     */
    public function offsetUnset(mixed $offset): void
    {
        if (!is_string($offset))
        {
            throw new NoSuchElementException(self::describeNonStringKey($offset));
        }

        $this->remove($offset);
    }


    // The message for being given a key that isn't a string, to read or unset.
    private static function describeNonStringKey(mixed $offset): string
    {
        return "A " . static::class . "'s keys are strings, not " . get_debug_type($offset) . ".";
    }
}
