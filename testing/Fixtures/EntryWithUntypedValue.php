<?php

/*
 * An entry whose constructor declares its value as mixed, which must be refused, as the value's type is read
 * from it.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractEntry;

/**
 * @extends AbstractEntry<mixed>
 */
final readonly class EntryWithUntypedValue extends AbstractEntry
{
    public function __construct(int|string $key, mixed $value)
    {
        parent::__construct($key, $value);
    }
}
