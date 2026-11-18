<?php

/*
 * Another entry class for users, so a map can be checked to refuse entries of a class that isn't its own.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractEntry;

/**
 * @extends AbstractEntry<User>
 */
final readonly class OtherUserEntry extends AbstractEntry
{
    public function __construct(int|string $key, User $user)
    {
        parent::__construct($key, $user);
    }
}
