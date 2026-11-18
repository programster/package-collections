<?php

/*
 * A map that takes users rather than entries, which must be refused, as a map holds entries, each with its key.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractReadonlyMap;

/**
 * @extends AbstractReadonlyMap<User, UserEntry>
 */
final readonly class MapOfUsers extends AbstractReadonlyMap
{
    public function __construct(User ...$users)
    {
        parent::__construct(...$users);
    }
}
