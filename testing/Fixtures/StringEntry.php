<?php

/*
 * An entry of a string, under a key, for checking an entry of a built-in type.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractEntry;

/**
 * @extends AbstractEntry<string>
 */
final readonly class StringEntry extends AbstractEntry
{
    public function __construct(int|string $key, string $value)
    {
        parent::__construct($key, $value);
    }
}
