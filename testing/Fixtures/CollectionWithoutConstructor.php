<?php

/*
 * A mutable collection with no constructor of its own, which must be refused, as every collection has to take its
 * elements as a typed variadic, so PHP checks their type where it's created.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractCollection;

/**
 * @extends AbstractCollection<User>
 */
final class CollectionWithoutConstructor extends AbstractCollection
{
    public function getType(): string { return User::class; }
}
