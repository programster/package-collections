<?php

/*
 * What the mutable and readonly collections share: checking an element is of the collection's type, and reading a
 * property or method from an element, for column(), sort() and where(). This is internal to the package, and may
 * change in any release.
 */

namespace Programster\Collections\Internal;

use Programster\Collections\Exceptions\InvalidPropertyOrMethod;
use Programster\Collections\Exceptions\UnsupportedOperationException;

/**
 * @internal
 */
trait ElementTools
{
    # The type names getType() can return, other than a class, interface or enum name.
    private const PSEUDO_TYPES = [
        'array', 'bool', 'boolean', 'callable', 'float', 'double', 'int', 'integer', 'null', 'numeric', 'object',
        'resource', 'scalar', 'string', 'mixed',
    ];

    /**
     * The type of element the collection holds: a class, interface or enum name, or one of "array", "bool" (or
     * "boolean"), "callable", "float" (or "double"), "int" (or "integer"), "null", "numeric", "object", "resource",
     * "scalar", "string" or "mixed".
     * @return string
     */
    abstract public function getType(): string;


    /**
     * Check that the collection's getType() names a type that exists, so a typo (e.g. "integr", or a class name that
     * isn't imported) is caught the first time the collection is created, rather than every element being rejected.
     * It's only checked once for each class, as it can't change.
     * @return void
     * @throws \LogicException - if getType() doesn't name a known type, or an existing class, interface or enum.
     */
    private function assertValidType(): void
    {
        /** @var array<class-string, true> $checkedClasses */
        static $checkedClasses = [];

        if (!isset($checkedClasses[static::class]))
        {
            $type = $this->getType();

            $isKnown = (
                   in_array($type, self::PSEUDO_TYPES, true)
                || class_exists($type)
                || interface_exists($type)
                || enum_exists($type)
            );

            if (!$isKnown)
            {
                throw new \LogicException(
                    static::class . "::getType() returns \"{$type}\", which isn't a class, interface or enum that " .
                    "exists, or one of: " . implode(", ", self::PSEUDO_TYPES) . "."
                );
            }

            $checkedClasses[static::class] = true;
        }
    }


    /**
     * Check whether a value is of a type.
     * @param mixed $value - the value.
     * @param string $type - a class, interface or enum name, or one of the type names getType() can return.
     * @return bool
     */
    private static function isOfType(mixed $value, string $type): bool
    {
        return match ($type) {
            'array' => is_array($value),
            'bool', 'boolean' => is_bool($value),
            'callable' => is_callable($value),
            'float', 'double' => is_float($value),
            'int', 'integer' => is_int($value),
            'null' => $value === null,
            'numeric' => is_numeric($value),
            'object' => is_object($value),
            'resource' => is_resource($value),
            'scalar' => is_scalar($value),
            'string' => is_string($value),
            'mixed' => true,
            default => $value instanceof $type,
        };
    }


    /**
     * Get the value to compare or return for an element: the element itself, or the value of one of its public
     * properties or methods (or, for an array, one of its keys). A property is used if there's both a public property
     * and a method with the name.
     *
     * @param mixed $element - the element.
     *
     * @param string|null $propertyOrMethod - the name of the public property, method or array key, or null for the
     * element itself.
     *
     * @return mixed
     *
     * @throws InvalidPropertyOrMethod - if the element has no such public property, method or key.
     * @throws UnsupportedOperationException - if a name is given, but the element is a scalar.
     */
    private function extractValue(mixed $element, ?string $propertyOrMethod): mixed
    {
        if ($propertyOrMethod === null)
        {
            $value = $element;
        }
        elseif (is_array($element))
        {
            if (!array_key_exists($propertyOrMethod, $element))
            {
                throw new InvalidPropertyOrMethod("There's no key \"{$propertyOrMethod}\" in the collection's arrays.");
            }

            $value = $element[$propertyOrMethod];
        }
        elseif (!is_object($element))
        {
            throw new UnsupportedOperationException(
                "A collection of {$this->getType()} doesn't support reading a property or method of its elements."
            );
        }
        elseif (self::hasPublicProperty($element, $propertyOrMethod))
        {
            $value = $element->$propertyOrMethod;
        }
        elseif (is_callable([$element, $propertyOrMethod]))
        {
            $value = $element->$propertyOrMethod();
        }
        else
        {
            throw new InvalidPropertyOrMethod(
                "There's no public property or method called \"{$propertyOrMethod}\" in " . get_class($element) . "."
            );
        }

        return $value;
    }


    /**
     * Get the elements of one list that aren't in another, compared with ===, so an object only matches itself.
     * @template TElement
     * @param array<TElement> $elements - the elements to check.
     * @param array<mixed> $others - the elements to look for them in.
     * @return list<TElement>
     */
    private static function elementsNotIn(array $elements, array $others): array
    {
        $notIn = [];

        foreach ($elements as $element)
        {
            if (!in_array($element, $others, true))
            {
                $notIn[] = $element;
            }
        }

        return $notIn;
    }


    /**
     * Check whether an object has a public property with a name, which a method of the same name doesn't count as.
     * @param object $object - the object.
     * @param string $name - the property's name.
     * @return bool
     */
    private static function hasPublicProperty(object $object, string $name): bool
    {
        $isDeclaredPublic = (property_exists($object, $name) && (new \ReflectionProperty($object, $name))->isPublic());
        return $isDeclaredPublic || array_key_exists($name, get_object_vars($object));
    }
}
