<?php

/*
 * An entry of a user, under a key, made the way the package's documentation says to.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractEntry;

/**
 * @extends AbstractEntry<User>
 */
final readonly class UserEntry extends AbstractEntry
{
    public function __construct(int|string $key, User $user)
    {
        parent::__construct($key, $user);
    }
}
