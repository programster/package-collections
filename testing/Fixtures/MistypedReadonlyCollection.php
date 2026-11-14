<?php

/*
 * A readonly collection whose getType() names a class that doesn't exist, as a class name that isn't imported would,
 * which must be refused.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractReadonlyCollection;

/**
 * @extends AbstractReadonlyCollection<User>
 */
final readonly class MistypedReadonlyCollection extends AbstractReadonlyCollection
{
    public function __construct(User ...$users)
    {
        parent::__construct(...$users);
    }


    public function getType(): string { return 'Usr'; }
}
