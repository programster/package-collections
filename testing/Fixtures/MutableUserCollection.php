<?php

/*
 * A mutable collection of users, made the way the package's documentation recommends, with a typed variadic
 * constructor.
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
        parent::__construct(...$users);
    }
}
