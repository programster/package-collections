<?php

/*
 * A readonly map of users, by key, made the way the package's documentation says to.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractReadonlyMap;

/**
 * @extends AbstractReadonlyMap<User>
 */
final readonly class UserMap extends AbstractReadonlyMap
{
    public function __construct(User ...$users)
    {
        parent::__construct(...$users);
    }


    public function getType(): string { return User::class; }
}
