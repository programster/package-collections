<?php

/*
 * A small object for the tests to put in collections, with a public property and a method to sort and read them by.
 */

namespace Programster\Collections\Testing\Fixtures;


final readonly class User
{
    public string $name;
    private int $m_age;


    public function __construct(string $name, int $age)
    {
        $this->name = $name;
        $this->m_age = $age;
    }


    # Accessors
    public function getAge(): int { return $this->m_age; }
}
