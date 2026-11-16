<?php

/*
 * A careless collection, whose constructor doesn't declare its elements' type at all, which must be refused.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractReadonlyCollection;

/**
 * @extends AbstractReadonlyCollection<User>
 */
final readonly class NoTypeUserCollection extends AbstractReadonlyCollection
{
    public function __construct(...$users)
    {
        parent::__construct(...$users);
    }
}
