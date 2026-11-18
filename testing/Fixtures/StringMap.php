<?php

/*
 * A readonly map of strings, by key, for checking a map of a built-in type.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractReadonlyMap;

/**
 * @extends AbstractReadonlyMap<string, StringEntry>
 */
final readonly class StringMap extends AbstractReadonlyMap
{
    public function __construct(StringEntry ...$entries)
    {
        parent::__construct(...$entries);
    }
}
