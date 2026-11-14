<?php

/*
 * A typed collection that can't be changed once it's created, for defensive programming: once a collection has been
 * checked and handed over, nothing can add to it, remove from it or replace its elements.
 *
 * It's similar to ramsey/collection's collections, so it'll feel familiar, but it isn't compatible with them. It has
 * none of the methods that would change it (add(), remove() and clear()): combine collections with merge() instead.
 * It also deliberately doesn't support array syntax (ArrayAccess), as $collection[] = $x could then only fail when it
 * runs, rather than being caught by PHP and the IDE as it's typed. Use get() to read an element by its position.
 *
 * To create one, extend it with a readonly class whose constructor takes just the elements, as a typed variadic, and
 * passes them on, and whose getType() returns the elements' class, e.g.:
 *
 *     final readonly class UserCollection extends AbstractReadonlyCollection
 *     {
 *         public function __construct(User ...$users)
 *         {
 *             parent::__construct(...$users);
 *         }
 *
 *         public function getType(): string { return User::class; }
 *     }
 *
 * The typed variadic means PHP rejects an element of the wrong type where the collection is created. The constructor
 * must take nothing else, as the methods that return a new collection create it with just the elements.
 */

namespace Programster\Collections;

use Programster\Collections\Exceptions\CollectionMismatchException;
use Programster\Collections\Exceptions\InvalidArgumentException;
use Programster\Collections\Exceptions\InvalidPropertyOrMethod;
use Programster\Collections\Exceptions\NoSuchElementException;
use Programster\Collections\Exceptions\OutOfBoundsException;
use Programster\Collections\Exceptions\UnsupportedOperationException;
use Programster\Collections\Internal\ElementTools;

/**
 * @template T
 * @implements \IteratorAggregate<int, T>
 */
abstract readonly class AbstractReadonlyCollection implements \Countable, \IteratorAggregate
{
    use ElementTools;

    /** @var list<T> */
    protected array $m_elements;


    /**
     * Create the collection from its elements. This is protected so that every collection has to declare its own
     * constructor, taking its elements as a typed variadic, so PHP checks their type where the collection is made.
     *
     * @param T ...$elements - the elements, in order. Any keys (e.g. from spreading an associative array) are
     * dropped, so the elements are always numbered from 0.
     *
     * @throws InvalidArgumentException - if an element isn't of the collection's type, which can only happen if the
     * subclass's constructor doesn't declare the type.
     *
     * @throws \LogicException - if the subclass's constructor takes anything other than one variadic parameter, as
     * the methods that return a new collection need to be able to create one from just its elements, or if its
     * getType() doesn't name a type that exists.
     */
    protected function __construct(mixed ...$elements)
    {
        self::assertValidConstructor(static::class);
        $this->assertValidType();
        $type = $this->getType();

        foreach ($elements as $element)
        {
            if (!self::isOfType($element, $type))
            {
                throw new InvalidArgumentException(
                    "A " . static::class . " can only hold {$type}, but was given " . get_debug_type($element) . "."
                );
            }
        }

        $this->m_elements = array_values($elements);
    }


    /**
     * Get the element at a position.
     * @param int $index - the position, starting from 0.
     * @return T
     * @throws OutOfBoundsException - if there is no element at that position.
     */
    public function get(int $index): mixed
    {
        if (!array_key_exists($index, $this->m_elements))
        {
            throw new OutOfBoundsException(
                "There is no element at index {$index}, as the collection has " . count($this->m_elements) . "."
            );
        }

        return $this->m_elements[$index];
    }


    /**
     * Get the first element.
     * @return T
     * @throws NoSuchElementException - if the collection is empty.
     */
    public function first(): mixed
    {
        if (count($this->m_elements) === 0)
        {
            throw new NoSuchElementException("Can't get the first element, as the collection is empty.");
        }

        return $this->m_elements[0];
    }


    /**
     * Get the last element.
     * @return T
     * @throws NoSuchElementException - if the collection is empty.
     */
    public function last(): mixed
    {
        if (count($this->m_elements) === 0)
        {
            throw new NoSuchElementException("Can't get the last element, as the collection is empty.");
        }

        return $this->m_elements[count($this->m_elements) - 1];
    }


    /**
     * Check whether the collection holds an element.
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
     * Get the value of a property or method from every element, in order.
     * @param string $propertyOrMethod - the name of a public property, or a public method that takes no arguments.
     * @return list<mixed>
     * @throws InvalidPropertyOrMethod - if an element has no such public property or method.
     * @throws UnsupportedOperationException - if the elements are scalars, which have no properties or methods.
     */
    public function column(string $propertyOrMethod): array
    {
        $values = [];

        foreach ($this->m_elements as $element)
        {
            $values[] = $this->extractValue($element, $propertyOrMethod);
        }

        return $values;
    }


    /**
     * Get a new collection with the elements sorted.
     *
     * @param string|null $propertyOrMethod - the public property or method to sort by, or null (the default) to
     * compare the elements themselves.
     *
     * @param Sort $order - the order to sort in. Defaults to ascending.
     *
     * @return static
     *
     * @throws InvalidPropertyOrMethod - if an element has no such public property or method.
     * @throws UnsupportedOperationException - if a property or method is given, but the elements are scalars.
     */
    public function sort(?string $propertyOrMethod = null, Sort $order = Sort::ASCENDING): static
    {
        $sorted = $this->m_elements;
        $direction = ($order === Sort::DESCENDING) ? -1 : 1;

        $compare = function (mixed $a, mixed $b) use ($propertyOrMethod, $direction): int {
            return ($this->extractValue($a, $propertyOrMethod) <=> $this->extractValue($b, $propertyOrMethod))
                * $direction;
        };

        usort($sorted, $compare);
        return new static(...$sorted);
    }


    /**
     * Get a new collection with only the elements that a callback accepts.
     * @param callable(T): bool $callback - returns true for each element to keep.
     * @return static
     */
    public function filter(callable $callback): static
    {
        return new static(...array_filter($this->m_elements, $callback));
    }


    /**
     * Get a new collection with only the elements whose property or method has a value, compared with ===.
     *
     * @param string|null $propertyOrMethod - the public property or method to check, or null to compare the elements
     * themselves.
     *
     * @param mixed $value - the value to keep the elements with.
     *
     * @return static
     *
     * @throws InvalidPropertyOrMethod - if an element has no such public property or method.
     * @throws UnsupportedOperationException - if a property or method is given, but the elements are scalars.
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
        return array_map($callback, $this->m_elements);
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
        return array_reduce($this->m_elements, $callback, $initial);
    }


    /**
     * Get a new collection with the elements that are in only one of this collection and another, compared with ===,
     * so an object only matches itself.
     * @param static $other - the collection to compare with, which must be of the same class.
     * @return static
     * @throws CollectionMismatchException - if the other collection is of a different class.
     */
    public function diff(self $other): static
    {
        $this->assertSameClass($other);
        $notInOther = self::elementsNotIn($this->m_elements, $other->m_elements);
        $notInThis = self::elementsNotIn($other->m_elements, $this->m_elements);
        return new static(...$notInOther, ...$notInThis);
    }


    /**
     * Get a new collection with the elements that are in both this collection and another, compared with ===, so an
     * object only matches itself.
     * @param static $other - the collection to compare with, which must be of the same class.
     * @return static
     * @throws CollectionMismatchException - if the other collection is of a different class.
     */
    public function intersect(self $other): static
    {
        $this->assertSameClass($other);

        $isInOther = function (mixed $element) use ($other): bool {
            return in_array($element, $other->m_elements, true);
        };

        return $this->filter($isInOther);
    }


    /**
     * Get a new collection with this collection's elements, followed by the other collections' elements, in order.
     * This is the way to add elements, as the collection can't be changed: merge in a new collection of them.
     * @param static ...$collections - the collections to add, which must be of the same class.
     * @return static
     * @throws CollectionMismatchException - if any of the collections is of a different class.
     */
    public function merge(self ...$collections): static
    {
        $elements = $this->m_elements;

        foreach ($collections as $collection)
        {
            $this->assertSameClass($collection);
            $elements = array_merge($elements, $collection->m_elements);
        }

        return new static(...$elements);
    }


    /**
     * Get the elements as an array, numbered from 0.
     * @return list<T>
     */
    public function toArray(): array
    {
        return $this->m_elements;
    }


    /**
     * Check whether the collection has no elements.
     * @return bool
     */
    public function isEmpty(): bool
    {
        return count($this->m_elements) === 0;
    }


    /**
     * Get the number of elements, which also lets count() be used on the collection.
     * @return int
     */
    public function count(): int
    {
        return count($this->m_elements);
    }


    /**
     * Get an iterator over the elements, which lets the collection be used in a foreach.
     * @return \ArrayIterator<int, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->m_elements);
    }


    /**
     * Check that another collection is of this collection's class, so the two hold the same type of element.
     * @param AbstractReadonlyCollection<mixed> $other - the other collection.
     * @return void
     * @throws CollectionMismatchException - if it's of a different class.
     */
    private function assertSameClass(AbstractReadonlyCollection $other): void
    {
        if (!($other instanceof static))
        {
            throw new CollectionMismatchException(
                "A " . static::class . " can only be combined with another " . static::class . ", not a " .
                get_class($other) . "."
            );
        }
    }


    /**
     * Check that a collection's constructor takes just its elements, as one variadic parameter, as the methods that
     * return a new collection create it with just the elements. This is only checked the first time each class is
     * created, as it can't change.
     * @param class-string $class - the collection's class.
     * @return void
     * @throws \LogicException - if the constructor takes anything else.
     */
    private static function assertValidConstructor(string $class): void
    {
        /** @var array<class-string, true> $checkedClasses */
        static $checkedClasses = [];

        if (!isset($checkedClasses[$class]))
        {
            $parameters = (new \ReflectionMethod($class, '__construct'))->getParameters();
            $isValid = (count($parameters) === 1 && $parameters[0]->isVariadic());

            if (!$isValid)
            {
                throw new \LogicException(
                    "{$class}'s constructor must take just its elements, as one typed variadic parameter (e.g. " .
                    "User ...\$users), as the methods that return a new collection create it with just the elements."
                );
            }

            $checkedClasses[$class] = true;
        }
    }
}
