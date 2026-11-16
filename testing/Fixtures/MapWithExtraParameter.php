<?php

/*
 * A map whose constructor wrongly takes something as well as its elements, which must be refused, as the methods
 * that return a new map create it with just the elements.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractReadonlyMap;

/**
 * @extends AbstractReadonlyMap<User>
 */
final readonly class MapWithExtraParameter extends AbstractReadonlyMap
{
    private string $m_label;


    public function __construct(string $label, User ...$users)
    {
        $this->m_label = $label;
        parent::__construct(...$users);
    }


    # Accessors
    public function getLabel(): string { return $this->m_label; }
}
