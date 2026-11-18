<?php

/*
 * An entry whose constructor takes something as well as the key and the value, which must be refused, as a map
 * creates entries from just a key and a value.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractEntry;

/**
 * @extends AbstractEntry<User>
 */
final readonly class EntryWithExtraParameter extends AbstractEntry
{
    public function __construct(int|string $key, User $user, string $label)
    {
        parent::__construct($key, $user);
    }
}
