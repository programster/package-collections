<?php

/*
 * A collection whose constructor allows two types of element, which must be refused (they'd share an interface).
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractReadonlyCollection;

/**
 * @extends AbstractReadonlyCollection<User>
 */
final readonly class UnionUserCollection extends AbstractReadonlyCollection
{
    public function __construct(User|\stdClass ...$users)
    {
        parent::__construct(...$users);
    }
}
