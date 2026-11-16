<?php

/*
 * A typed map, of elements looked up by a string key (e.g. users by their name), that can't be changed once it's
 * created. Methods that would change it (with() and without()) return a new map instead, and leave the original as it
 * was. Use one wherever a lookup table doesn't need to change after it's created.
 *
 * It's similar to ramsey/collection's typed maps, so it'll feel familiar, but it isn't compatible with them. It
 * deliberately doesn't support array syntax (ArrayAccess), as $map['key'] = $x could then only fail when it runs,
 * rather than being caught by PHP and the IDE as it's typed. Use get() and containsKey() instead.
 *
 * To create one, extend it with a readonly class whose constructor takes just the elements, as a typed variadic, and
 * passes them on. The elements' type is read from the constructor, e.g.:
 *
 *     final readonly class UserMap extends AbstractReadonlyMap
 *     {
 *         public function __construct(User ...$users)
 *         {
 *             parent::__construct(...$users);
 *         }
 *     }
 *
 * Then give it the elements by name, by spreading an associative array, or with named arguments:
 *
 *     $users = new UserMap(...['ann' => $ann, 'bob' => $bob]);
 *     $users = new UserMap(ann: $ann, bob: $bob);
 *
 * The typed variadic means PHP rejects an element of the wrong type where the map is created. Keys are always strings,
 * as only elements passed by name keep their keys: one passed without a key is rejected, rather than silently being
 * given a number.
 */

namespace Programster\Collections;

use Programster\Collections\Exceptions\InvalidArgumentException;
use Programster\Collections\Exceptions\NoSuchElementException;
use Programster\Collections\Internal\MapTools;

/**
 * @template T
 * @implements \IteratorAggregate<string, T>
 */
abstract readonly class AbstractReadonlyMap implements \Countable, \IteratorAggregate, \JsonSerializable
{
    use MapTools;

    /** @var array<string, T> */
    protected array $m_elements;


    /**
     * Create the map from its elements. This is protected so that every map has to declare its own constructor,
     * taking its elements as a typed variadic, so PHP checks their type where the map is made.
     *
     * @param T ...$elements - the elements, each given by its key (by spreading an associative array, or with named
     * arguments). They're kept in the order given.
     *
     * @throws InvalidArgumentException - if an element has no key (it was passed by position, or its key is a whole
     * number, which PHP turns into a position), or isn't of the map's type (which can only happen if the subclass's
     * constructor passes on something other than what it was given).
     *
     * @throws \LogicException - if the subclass's constructor takes anything other than one variadic parameter that
     * declares one type (see getType()), as the elements' type is read from it, and the methods that return a new map
     * need to be able to create one from just its elements.
     */
    protected function __construct(mixed ...$elements)
    {
        self::assertValidConstructor(static::class);

        /** @var array<string, T> $checked */
        $checked = $this->checkElements($elements);
        $this->m_elements = $checked;
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
     * Get a new map with an element added under a key, or replacing the one already there (which keeps its place).
     * This is the way to add an element, as the map can't be changed.
     *
     * @param string $key - the key.
     *
     * @param T $element - the element.
     *
     * @return static
     *
     * @throws InvalidArgumentException - if the element isn't of the map's type.
     */
    public function with(string $key, mixed $element): static
    {
        $this->checkValue($key, $element, $this->getType());
        $elements = $this->m_elements;
        $elements[$key] = $element;

        return new static(...$elements);
    }


    /**
     * Get a new map without the element under a key.
     *
     * @param string $key - the key.
     *
     * @return static
     *
     * @throws NoSuchElementException - if there's no element for the key, as removing it can't do anything, so it's
     * likely to be a mistake (check with containsKey() first, if there might not be one).
     */
    public function without(string $key): static
    {
        if (!array_key_exists($key, $this->m_elements))
        {
            $problem = "Can't remove \"{$key}\" from a " . static::class;
            throw new NoSuchElementException("{$problem}, as it isn't there.");
        }

        $elements = $this->m_elements;
        unset($elements[$key]);

        return new static(...$elements);
    }


    /**
     * Get a new map with only the elements that a callback accepts.
     * @param callable(T, string): bool $callback - given each element and its key, returns true to keep it.
     * @return static
     */
    public function filter(callable $callback): static
    {
        return new static(...array_filter($this->m_elements, $callback, ARRAY_FILTER_USE_BOTH));
    }


    /**
     * Apply a callback to every element, and get the results under the same keys. This returns an array rather than
     * a map, as the results can be of any type. Put them in a map of the results' type if needed.
     *
     * @template TResult
     *
     * @param callable(T, string): TResult $callback - given each element and its key, returns its result.
     *
     * @return array<string, TResult>
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
}
