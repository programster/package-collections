<?php

namespace Programster\Collections;

use Exception;


abstract class AbstractCollection extends \ArrayObject
{
    private string $m_elementType;


    public function __construct(string $elementType, ...$elements)
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


    public function append($value) : void
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


    public function offsetSet($key, $value) : void
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


    public function appendMultiple(iterable $newVals) : void
    {
        foreach ($newVals as $newVal)
        {
            $this->append($newVal);
        }
    }


    public function filter(callable $callback)
    {
        foreach ($this as $index => $item)
        {
            if ($callback($item) === false)
            {
                $this->offsetUnset($index);
            }
        }

        return $this;
    }
}
