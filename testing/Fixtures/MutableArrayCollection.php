<?php

/*
 * A mutable collection of arrays, for checking that column(), sort() and where() can read an array's keys.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractCollection;

/**
 * @extends AbstractCollection<array<string, mixed>>
 */
final class MutableArrayCollection extends AbstractCollection
{
    /**
     * @param array<string, mixed> ...$rows
     */
    public function __construct(array ...$rows)
    {
        parent::__construct(...$rows);
    }


    public function getType(): string { return 'array'; }
}
