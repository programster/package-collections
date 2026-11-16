<?php

/*
 * A typed array that can be read and changed with array syntax ($array[] = $x, $array[$key], unset($array[$key])),
 * counted, looped over and encoded as JSON. It's similar to ramsey/collection's ArrayInterface.
 */

namespace Programster\Collections;

/**
 * @template T
 * @extends \ArrayAccess<array-key, T>
 * @extends \IteratorAggregate<array-key, T>
 */
interface ArrayInterface extends \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * Remove every element.
     * @return void
     */
    public function clear(): void;


    /**
     * Get the elements as an array, with their keys.
     * @return array<array-key, T>
     */
    public function toArray(): array;


    /**
     * Check whether there are no elements.
     * @return bool
     */
    public function isEmpty(): bool;
}
