<?php

/*
 * An entry whose value is nullable, which must be refused, as a value's type is one type.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractEntry;

/**
 * @extends AbstractEntry<User|null>
 */
final readonly class EntryWithNullableValue extends AbstractEntry
{
    public function __construct(int|string $key, ?User $user)
    {
        parent::__construct($key, $user);
    }
}
