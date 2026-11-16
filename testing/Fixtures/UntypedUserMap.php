<?php

/*
 * A map whose constructor forgets to declare its elements' type, so that the base constructor's own check is what
 * rejects the wrong type.
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


    public function getType(): string { return User::class; }
}
