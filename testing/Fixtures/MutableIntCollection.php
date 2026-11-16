<?php

/*
 * A mutable collection of a built-in type (int), whose type is read from its constructor like a class's.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractCollection;

/**
 * @extends AbstractCollection<int>
 */
final class MutableIntCollection extends AbstractCollection
{
    public function __construct(int ...$ints)
    {
        parent::__construct(...$ints);
    }
}
