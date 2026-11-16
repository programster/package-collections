<?php

/*
 * A readonly collection of a scalar type, for checking that scalar types work.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractReadonlyCollection;

/**
 * @extends AbstractReadonlyCollection<string>
 */
final readonly class ReadonlyStringCollection extends AbstractReadonlyCollection
{
    public function __construct(string ...$strings)
    {
        parent::__construct(...$strings);
    }
}
