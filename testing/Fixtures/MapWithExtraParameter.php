<?php

/*
 * A map whose constructor wrongly takes something as well as its entries, which must be refused, as the methods
 * that return a new map create it with just the entries.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractReadonlyMap;

/**
 * @extends AbstractReadonlyMap<User, UserEntry>
 */
final readonly class MapWithExtraParameter extends AbstractReadonlyMap
{
    private string $m_label;


    public function __construct(string $label, UserEntry ...$entries)
    {
        $this->m_label = $label;
        parent::__construct(...$entries);
    }


    # Accessors
    public function getLabel(): string { return $this->m_label; }
}
