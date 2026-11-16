<?php

/*
 * What the mutable and readonly maps share: checking that every element was given a key and is of the map's type, and
 * that a map's constructor takes just its elements. This is internal to the package, and may change in any release.
 */

namespace Programster\Collections\Internal;

use Programster\Collections\Exceptions\InvalidArgumentException;

/**
 * @internal
 */
trait MapTools
{
    use ElementTools;


    /**
     * Check every element was given a string key, and is of the map's type, and return them keyed by those keys.
     *
     * Keys can only be strings, as the elements reach the constructor through a typed variadic, which only keeps the
     * keys of elements passed by name (e.g. by spreading an associative array, or with named arguments). An element
     * passed by position, or under a key PHP treats as a number (e.g. "123"), arrives without its key, so it's
     * rejected rather than silently given a number.
     *
     * @param array<mixed> $elements - the elements, as the constructor received them.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException - if an element has no key, or isn't of the map's type.
     */
    private function checkElements(array $elements): array
    {
        $type = $this->getType();
        $checked = [];

        foreach ($elements as $key => $element)
        {
            if (!is_string($key))
            {
                throw new InvalidArgumentException(
                    "Every element of a " . static::class . " needs a key, so pass them by name, e.g. "
                    . "new " . static::class . "(...['name' => \$element]). Keys that are whole numbers (e.g. \"123\") "
                    . "can't be used, as PHP turns them into positions."
                );
            }

            $this->checkValue($key, $element, $type);
            $checked[$key] = $element;
        }

        return $checked;
    }


    /**
     * Check a value is of the map's type.
     * @param string $key - the key it's for, for the message.
     * @param mixed $value - the value.
     * @param string $type - the map's type.
     * @return void
     * @throws InvalidArgumentException - if it isn't.
     */
    private function checkValue(string $key, mixed $value, string $type): void
    {
        if (!self::isOfType($value, $type))
        {
            throw new InvalidArgumentException(
                "A " . static::class . " can only hold {$type}, but was given " . get_debug_type($value)
                . " for \"{$key}\"."
            );
        }
    }


    /**
     * Check that a map's constructor takes just its elements, as one variadic parameter, as the methods that return a
     * new map create it with just the elements. This is only checked the first time each class is created, as it
     * can't change.
     *
     * @param class-string $class - the map's class.
     *
     * @return void
     *
     * @throws \LogicException - if the constructor takes anything else.
     */
    private static function assertValidMapConstructor(string $class): void
    {
        /** @var array<class-string, true> $checkedClasses */
        static $checkedClasses = [];

        if (!isset($checkedClasses[$class]))
        {
            $parameters = (new \ReflectionMethod($class, '__construct'))->getParameters();
            $isValid = (count($parameters) === 1 && $parameters[0]->isVariadic());

            if (!$isValid)
            {
                throw new \LogicException(
                    "{$class}'s constructor must take just its elements, as one typed variadic parameter (e.g. " .
                    "User ...\$users), as the methods that return a new map create it with just the elements."
                );
            }

            $checkedClasses[$class] = true;
        }
    }
}
