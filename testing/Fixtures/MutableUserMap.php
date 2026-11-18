<?php

/*
 * A mutable map of users, by key, made the way the package's documentation says to.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractMap;

/**
 * @extends AbstractMap<User, UserEntry>
 */
final class MutableUserMap extends AbstractMap
{
    public function __construct(UserEntry ...$entries)
    {
        parent::__construct(...$entries);
    }
}
