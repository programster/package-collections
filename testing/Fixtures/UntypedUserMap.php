<?php

/*
 * A map whose constructor declares its elements as mixed rather than their type, which must be refused, as the
 * elements' type is read from the constructor.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractMap;

/**
 * @extends AbstractMap<User>
 */
final class UntypedUserMap extends AbstractMap
{
    public function __construct(mixed ...$users)
    {
        parent::__construct(...$users);
    }
}
