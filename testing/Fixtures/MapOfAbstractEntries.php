<?php

/*
 * A map that takes any entry, which must be refused, as it would let entries with values of any type in.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractEntry;
use Programster\Collections\AbstractReadonlyMap;

/**
 * @extends AbstractReadonlyMap<mixed, AbstractEntry<mixed>>
 */
final readonly class MapOfAbstractEntries extends AbstractReadonlyMap
{
    public function __construct(AbstractEntry ...$entries)
    {
        parent::__construct(...$entries);
    }
}
