<?php

/*
 * A collection whose constructor allows its elements to be null, which must be refused.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractReadonlyCollection;

/**
 * @extends AbstractReadonlyCollection<User>
 */
final readonly class NullableUserCollection extends AbstractReadonlyCollection
{
    public function __construct(?User ...$users)
    {
        parent::__construct(...$users);
    }
}
