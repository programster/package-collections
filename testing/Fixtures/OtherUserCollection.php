<?php

/*
 * A second collection of users, of a different class to UserCollection, for checking that the two can't be combined.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractReadonlyCollection;

/**
 * @extends AbstractReadonlyCollection<User>
 */
final readonly class OtherUserCollection extends AbstractReadonlyCollection
{
    public function __construct(User ...$users)
    {
        parent::__construct(...$users);
    }


    public function getType(): string { return User::class; }
}
