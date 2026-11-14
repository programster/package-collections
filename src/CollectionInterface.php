<?php

/*
 * A typed collection that can be changed after it's created. It's similar to ramsey/collection's CollectionInterface,
 * so it'll feel familiar, but it isn't compatible with it. It's built around defensive programming, so in places it
 * works differently, to catch mistakes as early as possible, e.g. add() returns nothing, and remove() throws if the
 * element isn't there.
 */

namespace Programster\Collections;

use Programster\Collections\Exceptions\CollectionMismatchException;
use Programster\Collections\Exceptions\InvalidArgumentException;
use Programster\Collections\Exceptions\InvalidPropertyOrMethod;
use Programster\Collections\Exceptions\NoSuchElementException;
use Programster\Collections\Exceptions\UnsupportedOperationException;

/**
 * @template T
 * @extends ArrayInterface<T>
 */
interface CollectionInterface extends ArrayInterface
{
    /**
     * Add an element to the end.
     * @param T $element - the element to add.
     * @return void
     * @throws InvalidArgumentException - if the element isn't of the collection's type.
     */
    public function add(mixed $element): void;


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
    public function contains(mixed $element, bool $strict = true): bool;


    /**
     * The type of element the collection holds: a class, interface or enum name, or one of "array", "bool" (or
     * "boolean"), "callable", "float" (or "double"), "int" (or "integer"), "null", "numeric", "object", "resource",
     * "scalar", "string" or "mixed".
     * @return string
     */
    public function getType(): string;


    /**
     * Remove the first occurrence of an element, compared with ===. Use contains() first, if the collection might not
     * hold it.
     * @param T $element - the element to remove.
     * @return void
     * @throws NoSuchElementException - if the collection doesn't hold the element.
     */
    public function remove(mixed $element): void;


    /**
     * Get the value of a property or method from every element, in order.
     * @param string $propertyOrMethod - the name of a public property, a public method that takes no arguments, or
     * an array key.
     * @return list<mixed>
     * @throws InvalidPropertyOrMethod - if an element has no such public property, method or key.
     * @throws UnsupportedOperationException - if the elements are scalars, which have no properties or methods.
     */
    public function column(string $propertyOrMethod): array;


    /**
     * Get the first element.
     * @return T
     * @throws NoSuchElementException - if the collection is empty.
     */
    public function first(): mixed;


    /**
     * Get the last element.
     * @return T
     * @throws NoSuchElementException - if the collection is empty.
     */
    public function last(): mixed;


    /**
     * Get a new collection with the elements sorted, numbered from 0. This collection isn't changed.
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
    public function sort(?string $propertyOrMethod = null, Sort $order = Sort::ASCENDING): static;


    /**
     * Get a new collection with only the elements that a callback accepts, numbered from 0. This collection isn't
     * changed.
     * @param callable(T): bool $callback - returns true for each element to keep.
     * @return static
     */
    public function filter(callable $callback): static;


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
    public function where(?string $propertyOrMethod, mixed $value): static;


    /**
     * Apply a callback to every element, and get the results, as an array, as they can be of any type.
     * @param callable(T): mixed $callback - turns an element into a result.
     * @return list<mixed>
     */
    public function map(callable $callback): array;


    /**
     * Reduce the elements to a single value, by passing each one to a callback along with the result so far.
     * @template TCarry
     * @param callable(TCarry, T): TCarry $callback - takes the result so far and an element, and returns the new one.
     * @param TCarry $initial - the result to start from.
     * @return TCarry
     */
    public function reduce(callable $callback, mixed $initial): mixed;


    /**
     * Get a new collection with the elements that are in only one of this collection and another, compared with ===.
     * @param static $other - the collection to compare with, which must be of the same class.
     * @return static
     * @throws CollectionMismatchException - if the other collection is of a different class.
     */
    public function diff(CollectionInterface $other): static;


    /**
     * Get a new collection with the elements that are in both this collection and another, compared with ===.
     * @param static $other - the collection to compare with, which must be of the same class.
     * @return static
     * @throws CollectionMismatchException - if the other collection is of a different class.
     */
    public function intersect(CollectionInterface $other): static;


    /**
     * Get a new collection with this collection's elements, followed by the other collections' elements. Elements
     * with string keys keep them (and replace an element with the same key), while the rest are added on the end.
     * @param static ...$collections - the collections to add, which must be of the same class.
     * @return static
     * @throws CollectionMismatchException - if any of the collections is of a different class.
     */
    public function merge(CollectionInterface ...$collections): static;
}
