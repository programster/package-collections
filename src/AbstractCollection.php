<?php

namespace Programster\Collections;

use Exception;


/**
 * A typed collection that can be changed after it's created. For one that can't, see AbstractReadonlyCollection.
 *
 * @template T
 * @extends \ArrayObject<array-key, T>
 */
abstract class AbstractCollection extends \ArrayObject
{
    private string $m_elementType;


    /**
     * @param string $elementType - the class, interface or enum the elements must be.
     * @param T ...$elements - the elements.
     * @throws Exception - if an element isn't of the type.
     */
    public function __construct(string $elementType, mixed ...$elements)
    {
        $this->m_elementType = $elementType;

        foreach ($elements as $element)
        {
            if ($element instanceof $elementType === FALSE)
            {
                throw new Exception("Cannot append non " . $this->m_elementType . " to collection");
            }
        }

        parent::__construct($elements);
    }


    public function append(mixed $value) : void
    {
        if ($value instanceof $this->m_elementType)
        {
            parent::append($value);
        }
        else
        {
            throw new Exception("Cannot append non " . $this->m_elementType . " to collection");
        }
    }


    public function offsetSet(mixed $key, mixed $value) : void
    {
        if ($value instanceof $this->m_elementType)
        {
            parent::offsetSet($key, $value);
        }
        else
        {
            throw new Exception("Cannot append non " . $this->m_elementType . " to collection");
        }
    }


    /**
     * Add several elements to the end.
     * @param iterable<T> $newVals - the elements to add.
     * @return void
     * @throws Exception - if an element isn't of the collection's type.
     */
    public function appendMultiple(iterable $newVals) : void
    {
        foreach ($newVals as $newVal)
        {
            $this->append($newVal);
        }
    }


    /**
     * Remove the elements that a callback rejects, from this collection (it doesn't create a new one). Note that the
     * remaining elements keep their indexes, so there can be gaps.
     * @param callable $callback - returns false for each element to remove.
     * @return static - this collection.
     */
    public function filter(callable $callback) : static
    {
        # Find what to remove first, then remove it, as removing elements while looping over them skips the element
        # after each one removed, so two in a row to remove would leave the second.
        $indexesToRemove = [];

        foreach ($this as $index => $item)
        {
            if ($callback($item) === false)
            {
                $indexesToRemove[] = $index;
            }
        }

        foreach ($indexesToRemove as $index)
        {
            $this->offsetUnset($index);
        }

        return $this;
    }
}
