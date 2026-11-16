<?php

/*
 * A mutable map of users, by key, made the way the package's documentation says to.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractMap;

/**
 * @extends AbstractMap<User>
 */
final class MutableUserMap extends AbstractMap
{
    public function __construct(User ...$users)
    {
        parent::__construct(...$users);
    }
}
