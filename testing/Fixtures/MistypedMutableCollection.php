<?php

/*
 * A mutable collection whose getType() names a type that doesn't exist, as a typo would, which must be refused.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractCollection;

/**
 * @extends AbstractCollection<int>
 */
final class MistypedMutableCollection extends AbstractCollection
{
    public function __construct(int ...$ints)
    {
        parent::__construct(...$ints);
    }


    public function getType(): string { return 'integr'; }
}
