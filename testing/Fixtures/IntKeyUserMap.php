<?php

/*
 * A readonly map of users whose entries only allow int keys.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractReadonlyMap;

/**
 * @extends AbstractReadonlyMap<User, IntKeyEntry>
 */
final readonly class IntKeyUserMap extends AbstractReadonlyMap
{
    public function __construct(IntKeyEntry ...$entries)
    {
        parent::__construct(...$entries);
    }
}
