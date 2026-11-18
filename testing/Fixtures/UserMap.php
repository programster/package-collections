<?php

/*
 * A readonly map of users, by key, made the way the package's documentation says to.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractReadonlyMap;

/**
 * @extends AbstractReadonlyMap<User, UserEntry>
 */
final readonly class UserMap extends AbstractReadonlyMap
{
    public function __construct(UserEntry ...$entries)
    {
        parent::__construct(...$entries);
    }
}
