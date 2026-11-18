<?php

/*
 * What collections, maps and entries share for types: which types a constructor can declare its elements (or an
 * entry's value) as, and checking a value is of one. This is internal to the package, and may change in any release.
 */

namespace Programster\Collections\Internal;

/**
 * @internal
 */
trait TypeTools
{
    # The built-in types a constructor can declare its elements as, as well as a class, interface or enum. The rest
    # (e.g. mixed, null, or iterable) can't be told apart, or hold anything, so they're refused.
    private const BUILT_IN_TYPES = ['array', 'bool', 'callable', 'float', 'int', 'object', 'string'];


    /**
     * Check whether a declared type is one type that values can be checked against: a class, interface or enum, or
     * one of BUILT_IN_TYPES, and not nullable, a union, or self, static or parent.
     * @param \ReflectionType|null $type - the declared type, or null if none is declared.
     * @return bool
     */
    private static function isOneDeclaredType(?\ReflectionType $type): bool
    {
        return (
               $type instanceof \ReflectionNamedType
            && !$type->allowsNull()
            && ($type->isBuiltin() ? in_array($type->getName(), self::BUILT_IN_TYPES, true) : true)
            && !in_array($type->getName(), ['self', 'static', 'parent'], true)
        );
    }


    /**
     * Check whether a value is of a type.
     * @param mixed $value - the value.
     * @param string $type - a class, interface or enum name, or one of BUILT_IN_TYPES.
     * @return bool
     */
    private static function isOfType(mixed $value, string $type): bool
    {
        return match ($type) {
            'array' => is_array($value),
            'bool' => is_bool($value),
            'callable' => is_callable($value),
            'float' => is_float($value),
            'int' => is_int($value),
            'object' => is_object($value),
            'string' => is_string($value),
            default => $value instanceof $type,
        };
    }
}
