<?php

/*
 * An entry whose key is declared as a float, which must be refused, as keys are ints or strings.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractEntry;

/**
 * @extends AbstractEntry<User>
 */
final readonly class EntryWithFloatKey extends AbstractEntry
{
    public function __construct(float $key, User $user)
    {
        parent::__construct((int)$key, $user);
    }
}
