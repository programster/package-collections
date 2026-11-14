<?php

/*
 * A mutable collection of a scalar type, named "integer" rather than "int", which getType() also allows.
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


    public function getType(): string { return 'integer'; }
}
