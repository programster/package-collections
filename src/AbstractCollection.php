<?php

/*
 * A typed collection that can be changed after it's created. It's similar to ramsey/collection's AbstractCollection,
 * so it'll feel familiar, but it isn't compatible with it. It's built around defensive programming, so in places it
 * works differently, to catch mistakes as early as possible (see CollectionInterface). Prefer
 * AbstractReadonlyCollection unless the collection genuinely needs to change.
 *
 * To create one, extend it with a class whose constructor takes the elements as a typed variadic, and passes them on,
 * so that PHP rejects an element of the wrong type where the collection is created, and whose getType() returns the
 * elements' type, e.g.:
 *
 *     final class UserCollection extends AbstractCollection
 *     {
 *         public function __construct(User ...$users)
 *         {
 *             parent::__construct(...$users);
 *         }
 *
 *         public function getType(): string { return User::class; }
 *     }
 */

namespace Programster\Collections;

use Programster\Collections\Exceptions\CollectionMismatchException;
use Programster\Collections\Exceptions\InvalidArgumentException;
use Programster\Collections\Exceptions\NoSuchElementException;
use Programster\Collections\Exceptions\OutOfBoundsException;
use Programster\Collections\Internal\ElementTools;

/**
 * @template T
 * @implements CollectionInterface<T>
 */
abstract class AbstractCollection implements CollectionInterface
{
    use ElementTools;

    /** @var array<array-key, T> */
    protected array $m_data = [];


    /**
     * Create the collection from its elements. This is protected so that every collection has to declare its own
     * constructor, taking its elements as a typed variadic, so PHP checks their type where the collection is made.
     *
     * @param T ...$elements - the elements, in order. Elements given string keys (e.g. by spreading an associative
     * array, or with named arguments) keep them, and the rest are numbered from 0.
     *
     * @throws InvalidArgumentException - if an element isn't of the collection's type, which can only happen if the
     * subclass's constructor doesn't declare the type.
     *
     * @throws \LogicException - if the subclass's getType() doesn't name a type that exists.
     */
    protected function __construct(mixed ...$elements)
    {
        $this->assertValidType();

        foreach ($elements as $key => $value)
        {
            $this->offsetSet(is_string($key) ? $key : null, $value);
        }
    }


    /**
     * Add an element to the end.
     * @param T $element - the element to add.
     * @return void
     * @throws InvalidArgumentException - if the element isn't of the collection's type.
     */
    public function add(mixed $element): void
    {
        $this->offsetSet(null, $element);
    }


    /**
     * Remove the first occurrence of an element, compared with ===. The other elements keep their keys. Use
     * contains() first, if the collection might not hold it.
     * @param T $element - the element to remove.
     * @return void
     * @throws NoSuchElementException - if the collection doesn't hold the element.
     */
    public function remove(mixed $element): void
    {
        $key = array_search($element, $this->m_data, true);

        if ($key === false)
        {
            throw new NoSuchElementException(
                "Can't remove the " . get_debug_type($element) . ", as the collection doesn't hold it."
            );
        }

        unset($this->m_data[$key]);
    }


    /**
     * Remove every element.
     * @return void
     */
    public function clear(): void
    {
        $this->m_data = [];
    }


    /**
     * Check whether the collection holds an element.
     *
     * @param T $element - the element to look for.
     *
     * @param bool $strict - whether to compare with === (the default), so an object only matches itself, rather than
     * with ==, where an object matches any equal object of the same class.
     *
     * @return bool
     */
    public function contains(mixed $element, bool $strict = true): bool
    {
        return in_array($element, $this->m_data, $strict);
    }


    /**
     * Get the value of a property or method from every element, in order.
     * @param string $propertyOrMethod - the name of a public property, a public method that takes no arguments, or
     * an array key.
     * @return list<mixed>
     */
    public function column(string $propertyOrMethod): array
    {
        $values = [];

        foreach ($this->m_data as $element)
        {
            $values[] = $this->extractValue($element, $propertyOrMethod);
        }

        return $values;
    }


    /**
     * Get the first element.
     * @return T
     * @throws NoSuchElementException - if the collection is empty.
     */
    public function first(): mixed
    {
        $firstKey = array_key_first($this->m_data);

        if ($firstKey === null)
        {
            throw new NoSuchElementException("Can't get the first element, as the collection is empty.");
        }

        return $this->m_data[$firstKey];
    }


    /**
     * Get the last element.
     * @return T
     * @throws NoSuchElementException - if the collection is empty.
     */
    public function last(): mixed
    {
        $lastKey = array_key_last($this->m_data);

        if ($lastKey === null)
        {
            throw new NoSuchElementException("Can't get the last element, as the collection is empty.");
        }

        return $this->m_data[$lastKey];
    }


    /**
     * Get a new collection with the elements sorted, numbered from 0. This collection isn't changed.
     * @param string|null $propertyOrMethod - the public property or method to sort by, or null (the default) to
     * compare the elements themselves.
     * @param Sort $order - the order to sort in. Defaults to ascending.
     * @return static
     */
    public function sort(?string $propertyOrMethod = null, Sort $order = Sort::ASCENDING): static
    {
        $sorted = $this->m_data;
        $direction = ($order === Sort::DESCENDING) ? -1 : 1;

        $compare = function (mixed $a, mixed $b) use ($propertyOrMethod, $direction): int {
            return ($this->extractValue($a, $propertyOrMethod) <=> $this->extractValue($b, $propertyOrMethod))
                * $direction;
        };

        usort($sorted, $compare);
        return $this->withData($sorted);
    }


    /**
     * Get a new collection with only the elements that a callback accepts, numbered from 0. This collection isn't
     * changed.
     * @param callable(T): bool $callback - returns true for each element to keep.
     * @return static
     */
    public function filter(callable $callback): static
    {
        return $this->withData(array_values(array_filter($this->m_data, $callback)));
    }


    /**
     * Get a new collection with only the elements whose property or method has a value, compared with ===.
     * @param string|null $propertyOrMethod - the public property or method to check, or null to compare the elements
     * themselves.
     * @param mixed $value - the value to keep the elements with.
     * @return static
     */
    public function where(?string $propertyOrMethod, mixed $value): static
    {
        $hasValue = function (mixed $element) use ($propertyOrMethod, $value): bool {
            return $this->extractValue($element, $propertyOrMethod) === $value;
        };

        return $this->filter($hasValue);
    }


    /**
     * Apply a callback to every element, and get the results. This returns an array rather than a collection, as
     * the results can be of any type. Put them in a collection of the results' type if needed.
     * @param callable(T): mixed $callback - turns an element into a result.
     * @return list<mixed>
     */
    public function map(callable $callback): array
    {
        return array_values(array_map($callback, $this->m_data));
    }


    /**
     * Reduce the elements to a single value, by passing each one to a callback along with the result so far.
     * @template TCarry
     * @param callable(TCarry, T): TCarry $callback - takes the result so far and an element, and returns the new one.
     * @param TCarry $initial - the result to start from.
     * @return TCarry
     */
    public function reduce(callable $callback, mixed $initial): mixed
    {
        return array_reduce($this->m_data, $callback, $initial);
    }


    /**
     * Get a new collection with the elements that are in only one of this collection and another, compared with ===,
     * numbered from 0.
     * @param static $other - the collection to compare with, which must be of the same class.
     * @return static
     * @throws CollectionMismatchException - if the other collection is of a different class.
     */
    public function diff(CollectionInterface $other): static
    {
        $this->assertSameClass($other);
        $otherData = $other->toArray();
        $notInOther = self::elementsNotIn($this->m_data, $otherData);
        $notInThis = self::elementsNotIn($otherData, $this->m_data);
        return $this->withData(array_merge($notInOther, $notInThis));
    }


    /**
     * Get a new collection with the elements that are in both this collection and another, compared with ===. They
     * keep their keys.
     * @param static $other - the collection to compare with, which must be of the same class.
     * @return static
     * @throws CollectionMismatchException - if the other collection is of a different class.
     */
    public function intersect(CollectionInterface $other): static
    {
        $this->assertSameClass($other);
        $otherData = $other->toArray();

        $isInOther = function (mixed $element) use ($otherData): bool {
            return in_array($element, $otherData, true);
        };

        return $this->withData(array_filter($this->m_data, $isInOther));
    }


    /**
     * Get a new collection with this collection's elements, followed by the other collections' elements. Elements
     * with string keys keep them (and replace an element with the same key), while the rest are added on the end.
     * This collection isn't changed.
     * @param static ...$collections - the collections to add, which must be of the same class.
     * @return static
     * @throws CollectionMismatchException - if any of the collections is of a different class.
     */
    public function merge(CollectionInterface ...$collections): static
    {
        $merged = $this->m_data;

        foreach ($collections as $collection)
        {
            $this->assertSameClass($collection);

            foreach ($collection->toArray() as $key => $value)
            {
                if (is_int($key))
                {
                    $merged[] = $value;
                }
                else
                {
                    $merged[$key] = $value;
                }
            }
        }

        return $this->withData($merged);
    }


    /**
     * Get the elements as an array, with their keys.
     * @return array<array-key, T>
     */
    public function toArray(): array
    {
        return $this->m_data;
    }


    /**
     * Check whether the collection has no elements.
     * @return bool
     */
    public function isEmpty(): bool
    {
        return count($this->m_data) === 0;
    }


    /**
     * Get the number of elements, which also lets count() be used on the collection.
     * @return int
     */
    public function count(): int
    {
        return count($this->m_data);
    }


    /**
     * Get an iterator over a copy of the elements, which lets the collection be used in a foreach. Changing the
     * collection during the loop doesn't change what the loop goes over.
     * @return \ArrayIterator<array-key, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->m_data);
    }


    /**
     * Check whether there's an element with a key, which lets isset() be used on the collection. Unlike isset() on an
     * array, this is true for a null element.
     * @param mixed $offset - the key.
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return (is_int($offset) || is_string($offset)) && array_key_exists($offset, $this->m_data);
    }


    /**
     * Get the element with a key, which lets $collection[$key] be used.
     * @param mixed $offset - the key.
     * @return T
     * @throws OutOfBoundsException - if there's no element with that key.
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!is_int($offset) && !is_string($offset))
        {
            throw new OutOfBoundsException("There is no element with a key of type " . get_debug_type($offset) . ".");
        }

        if (!array_key_exists($offset, $this->m_data))
        {
            throw new OutOfBoundsException("There is no element with the key {$offset}.");
        }

        return $this->m_data[$offset];
    }


    /**
     * Set the element with a key, or add one to the end if the key is null, which lets $collection[$key] = $x and
     * $collection[] = $x be used.
     * @param mixed $offset - the key, or null to add the element to the end.
     * @param T $value - the element.
     * @return void
     * @throws InvalidArgumentException - if the element isn't of the collection's type, or the key isn't an int,
     * string or null.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $type = $this->getType();

        if (!self::isOfType($value, $type))
        {
            throw new InvalidArgumentException(
                "A " . static::class . " can only hold {$type}, but was given " . get_debug_type($value) . "."
            );
        }

        if ($offset === null)
        {
            $this->m_data[] = $value;
        }
        elseif (is_int($offset) || is_string($offset))
        {
            $this->m_data[$offset] = $value;
        }
        else
        {
            throw new InvalidArgumentException(
                "A collection's keys must be ints or strings, but was given " . get_debug_type($offset) . "."
            );
        }
    }


    /**
     * Remove the element with a key, if there is one, which lets unset($collection[$key]) be used. As with an array,
     * unsetting a key that isn't there does nothing.
     * @param mixed $offset - the key.
     * @return void
     * @throws InvalidArgumentException - if the key isn't an int or string, as no element could have it.
     */
    public function offsetUnset(mixed $offset): void
    {
        if (!is_int($offset) && !is_string($offset))
        {
            throw new InvalidArgumentException(
                "A collection's keys must be ints or strings, but was given " . get_debug_type($offset) . "."
            );
        }

        unset($this->m_data[$offset]);
    }


    /**
     * Get what to serialize: the elements, with their keys.
     * @return array<array-key, T>
     */
    public function __serialize(): array
    {
        return $this->m_data;
    }


    /**
     * Restore the elements when unserialized, checking each is of the collection's type, so a changed serialized
     * string can't put an element of the wrong type in it.
     * @param array<array-key, T> $data - the elements, with their keys.
     * @return void
     * @throws InvalidArgumentException - if an element isn't of the collection's type.
     */
    public function __unserialize(array $data): void
    {
        $this->m_data = [];

        foreach ($data as $key => $value)
        {
            $this->offsetSet($key, $value);
        }
    }


    /**
     * Create a collection of this class with other elements, for the methods that return a new collection. It's a
     * clone, so this collection isn't changed, and a subclass's constructor can take any parameters.
     * @param array<array-key, T> $data - the elements, already of the collection's type.
     * @return static
     */
    protected function withData(array $data): static
    {
        $collection = clone $this;
        $collection->m_data = $data;
        return $collection;
    }


    /**
     * Check that another collection is of this collection's class, so the two hold the same type of element.
     * @param CollectionInterface<mixed> $other - the other collection.
     * @return void
     * @throws CollectionMismatchException - if it's of a different class.
     */
    private function assertSameClass(CollectionInterface $other): void
    {
        if (!($other instanceof static))
        {
            throw new CollectionMismatchException(
                "A " . static::class . " can only be combined with another " . static::class . ", not a " .
                get_class($other) . "."
            );
        }
    }
}
