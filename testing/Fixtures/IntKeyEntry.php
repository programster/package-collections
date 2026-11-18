<?php

/*
 * An entry whose key can only be an int, which is allowed, as is a key that can only be a string.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractEntry;

/**
 * @extends AbstractEntry<User>
 */
final readonly class IntKeyEntry extends AbstractEntry
{
    public function __construct(int $id, User $user)
    {
        parent::__construct($id, $user);
    }
}
