<?php

/*
 * A collection whose constructor takes more than its elements, which the base class must reject, as its methods that
 * return a new collection create it with just the elements.
 */

namespace Programster\Collections\Testing\Fixtures;

use Programster\Collections\AbstractReadonlyCollection;

/**
 * @extends AbstractReadonlyCollection<User>
 */
final readonly class CollectionWithExtraParameter extends AbstractReadonlyCollection
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
