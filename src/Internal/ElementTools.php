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
    use TypeTools;


    /**
     * The type of element the collection holds: the type its constructor declares its elements as, e.g. "User" for
     * User ...$users. It's a class, interface or enum name, or one of "array", "bool", "callable", "float", "int",
     * "object" or "string". It's read from the constructor, so it can't be written wrong, and only once for each
     * class, as it can't change.
     *
     * @return string
     *
     * @throws \LogicException - if the constructor doesn't take its elements as its last parameter, a variadic that
     * declares one type (see readElementType()).
     */
    final public function getType(): string
    {
        return self::readElementType(static::class);
    }


    /**
     * Check a readonly collection's (or a map's) constructor takes just its elements, as one variadic parameter that
     * declares one type (see readElementType()), as the methods that return a new one create it with just the
     * elements, so a class written wrong is refused the first time it's created. It's only checked once for each class,
     * as it can't change.
     *
     * @param class-string $class - the collection's class.
     *
     * @return void
     *
     * @throws \LogicException - if it doesn't.
     */
    private static function assertValidConstructor(string $class): void
    {
        $numParameters = (new \ReflectionMethod($class, '__construct'))->getNumberOfParameters();

        if ($numParameters !== 1)
        {
            throw new \LogicException(
                "{$class}'s constructor must take just its elements, as one typed variadic parameter (e.g. " .
                "User ...\$users), as the methods that return a new one create it with just the elements."
            );
        }

        self::readElementType($class);
    }


    /**
     * Check a mutable collection's constructor takes its elements as its last parameter, a variadic that declares one
     * type (see readElementType()). It can take other values before them, as its methods that return a new collection
     * copy it, rather than creating one. It's only checked once for each class, as it can't change.
     * @param class-string $class - the collection's class.
     * @return void
     * @throws \LogicException - if it doesn't.
     */
    private static function assertTypedConstructor(string $class): void
    {
        self::readElementType($class);
    }


    /**
     * Read the type a collection's (or map's) constructor declares its elements as. It takes the elements as its last
     * parameter, a variadic, which must declare one type, which isn't nullable. Elements of two kinds share an
     * interface instead. It's only read once for each class, as it can't change.
     *
     * @param class-string $class - the collection's class.
     *
     * @return string - the type (see getType()).
     *
     * @throws \LogicException - if the constructor's last parameter isn't a variadic, or doesn't declare one type.
     */
    private static function readElementType(string $class): string
    {
        /** @var array<class-string, string> $types */
        static $types = [];

        if (!isset($types[$class]))
        {
            $parameters = (new \ReflectionMethod($class, '__construct'))->getParameters();
            $last = end($parameters);

            if ($last === false || !$last->isVariadic())
            {
                throw new \LogicException(
                    "{$class}'s constructor must take its elements as its last parameter, a typed variadic (e.g. " .
                    "User ...\$users), as their type is read from it."
                );
            }

            $type = $last->getType();

            if (!self::isOneDeclaredType($type) || !($type instanceof \ReflectionNamedType))
            {
                $declared = ($type === null) ? "no type" : "\"{$type}\"";

                throw new \LogicException(
                    "{$class}'s constructor must declare the one type its elements are (e.g. User ...\$users), but " .
                    "declares {$declared}. It can be a class, interface or enum (use an interface for elements of " .
                    "more than one class), or one of: " . implode(", ", self::BUILT_IN_TYPES) . ". It can't be " .
                    "nullable."
                );
            }

            $types[$class] = $type->getName();
        }

        return $types[$class];
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
