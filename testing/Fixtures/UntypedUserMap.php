<?php

/*
 * A map whose constructor declares its entries as mixed rather than its entry class, which must be refused, as the
 * entries' type is read from the constructor.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractMap;

/**
 * @extends AbstractMap<User, UserEntry>
 */
final class UntypedUserMap extends AbstractMap
{
    public function __construct(mixed ...$entries)
    {
        parent::__construct(...$entries);
    }
}
