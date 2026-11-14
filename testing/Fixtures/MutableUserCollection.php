<?php

/*
 * A collection of users using the mutable AbstractCollection, for testing it.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractCollection;

/**
 * @extends AbstractCollection<User>
 */
final class MutableUserCollection extends AbstractCollection
{
    public function __construct(User ...$users)
    {
        parent::__construct(User::class, ...$users);
    }
}
