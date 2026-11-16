<?php

/*
 * A careless mutable collection, whose constructor doesn't declare its elements' type, which must be refused.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractCollection;

/**
 * @extends AbstractCollection<User>
 */
final class NoTypeMutableCollection extends AbstractCollection
{
    public function __construct(...$users)
    {
        parent::__construct(...$users);
    }
}
