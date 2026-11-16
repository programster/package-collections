<?php

/*
 * A mutable collection whose constructor takes more than its elements, to check that the methods that return a new
 * collection keep its other values.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractCollection;

/**
 * @extends AbstractCollection<User>
 */
final class LabelledUserCollection extends AbstractCollection
{
    private readonly string $m_label;


    public function __construct(string $label, User ...$users)
    {
        $this->m_label = $label;
        parent::__construct(...$users);
    }


    # Accessors
    public function getLabel(): string { return $this->m_label; }
}
