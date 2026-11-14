<?php

/*
 * A careless collection, whose constructor doesn't declare its elements' type, for checking that the base class
 * still rejects an element of the wrong type.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractReadonlyCollection;

/**
 * @extends AbstractReadonlyCollection<User>
 */
final readonly class UntypedUserCollection extends AbstractReadonlyCollection
{
    public function __construct(mixed ...$users)
    {
        parent::__construct(...$users);
    }


    public function getType(): string { return User::class; }
}
