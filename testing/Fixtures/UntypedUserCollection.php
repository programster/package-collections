<?php

/*
 * A careless collection, whose constructor declares its elements as mixed rather than their type, which must be
 * refused, as the elements' type is read from the constructor.
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
}
