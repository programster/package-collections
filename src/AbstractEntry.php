<?php

/*
 * One entry in a map: a key, and the value kept under it. Every map holds entries of its own class, which declares the
 * type of its values, so PHP checks a value's type, and that it was given a key, on the line that creates the entry.
 *
 * To create one, extend it with a readonly class whose constructor takes the key, and then the value, declared as its
 * one type, and passes them on, e.g.:
 *
 *     final readonly class InvoiceEntry extends AbstractEntry
 *     {
 *         public function __construct(int|string $key, Invoice $invoice)
 *         {
 *             parent::__construct($key, $invoice);
 *         }
 *     }
 *
 *     $entry = new InvoiceEntry(12, $invoice);
 *
 * The values' type is read from the constructor, so it can't be written wrong. The constructor must take just those
 * two parameters, as a map creates entries of its class from a key and a value (e.g. for $map['key'] = $value).
 */

namespace Programster\Collections;

use Programster\Collections\Exceptions\InvalidArgumentException;
use Programster\Collections\Internal\TypeTools;

/**
 * @template TValue
 */
abstract readonly class AbstractEntry
{
    use TypeTools;

    protected int|string $m_key;

    /** @var TValue */
    protected mixed $m_value;


    /**
     * Create the entry. This is protected so that every entry class has to declare its own constructor, declaring its
     * value's type, so PHP checks it where the entry is made.
     *
     * @param int|string $key - the key to keep the value under in a map. PHP treats a string that's a whole number
     * (e.g. "123") as the same key as that number, so a map can't hold both.
     *
     * @param TValue $value - the value.
     *
     * @throws InvalidArgumentException - if the value isn't of the entry's type, which can only happen if the
     * subclass's constructor passes on something other than what it was given.
     *
     * @throws \LogicException - if the subclass's constructor takes anything other than the key (declared as int,
     * string or int|string) and then the value (declared as one type), as the value's type is read from it, and a map
     * creates entries from just a key and a value.
     */
    protected function __construct(int|string $key, mixed $value)
    {
        $type = self::readValueType(static::class);

        if (!self::isOfType($value, $type))
        {
            throw new InvalidArgumentException(
                "A " . static::class . " can only hold {$type}, but was given " . get_debug_type($value) . "."
            );
        }

        $this->m_key = $key;
        $this->m_value = $value;
    }


    /**
     * The type of value the entry holds: the type its constructor declares its value as, e.g. "Invoice" for
     * Invoice $invoice. It's a class, interface or enum name, or one of "array", "bool", "callable", "float", "int",
     * "object" or "string".
     *
     * @return string
     *
     * @throws \LogicException - if the constructor doesn't take just the key and the value, declared as one type.
     */
    final public static function getValueType(): string
    {
        return self::readValueType(static::class);
    }


    /**
     * Get the value. (It has a docblock, unlike the other accessors, so static analysis knows the value's type.)
     * @return TValue
     */
    public function getValue(): mixed
    {
        return $this->m_value;
    }


    /**
     * Read the type an entry class's constructor declares its value as, checking the constructor takes just the key
     * and the value. It's only read once for each class, as it can't change.
     *
     * @param class-string $class - the entry's class.
     *
     * @return string - the type (see getValueType()).
     *
     * @throws \LogicException - if the constructor doesn't take just the key and the value, declared as one type.
     */
    private static function readValueType(string $class): string
    {
        /** @var array<class-string, string> $types */
        static $types = [];

        if (!isset($types[$class]))
        {
            $parameters = (new \ReflectionMethod($class, '__construct'))->getParameters();
            $example = "e.g. public function __construct(int|string \$key, Invoice \$invoice)";

            if (count($parameters) !== 2 || $parameters[0]->isVariadic() || $parameters[1]->isVariadic())
            {
                throw new \LogicException(
                    "{$class}'s constructor must take just the key and then the value, {$example}, as a map creates "
                    . "entries from just a key and a value."
                );
            }

            if (!self::isKeyType($parameters[0]->getType()))
            {
                throw new \LogicException(
                    "{$class}'s constructor must declare its key as int|string (or just int, or just string), "
                    . "{$example}, but declares " . self::describeDeclaredType($parameters[0]->getType()) . "."
                );
            }

            $valueType = $parameters[1]->getType();

            if (!self::isOneDeclaredType($valueType) || !($valueType instanceof \ReflectionNamedType))
            {
                throw new \LogicException(
                    "{$class}'s constructor must declare the one type its value is, {$example}, but declares "
                    . self::describeDeclaredType($valueType) . ". It can be a class, interface or enum (use an "
                    . "interface for values of more than one class), or one of: " . implode(", ", self::BUILT_IN_TYPES)
                    . ". It can't be nullable."
                );
            }

            $types[$class] = $valueType->getName();
        }

        return $types[$class];
    }


    /**
     * Check whether a key's declared type is int, string or int|string, the types a key can be.
     * @param \ReflectionType|null $type - the declared type, or null if none is declared.
     * @return bool
     */
    private static function isKeyType(?\ReflectionType $type): bool
    {
        $names = [];

        if ($type instanceof \ReflectionNamedType)
        {
            $names = [$type->getName()];
        }
        elseif ($type instanceof \ReflectionUnionType)
        {
            foreach ($type->getTypes() as $member)
            {
                $names[] = ($member instanceof \ReflectionNamedType) ? $member->getName() : '';
            }
        }

        sort($names);

        return (
               ($type === null || !$type->allowsNull())
            && in_array($names, [['int'], ['string'], ['int', 'string']], true)
        );
    }


    /**
     * Describe a declared type for a message, e.g. "\"mixed\"", or "no type" if none is declared.
     * @param \ReflectionType|null $type - the declared type, or null.
     * @return string
     */
    private static function describeDeclaredType(?\ReflectionType $type): string
    {
        return ($type === null) ? "no type" : "\"{$type}\"";
    }


    # Accessors
    public function getKey(): int|string { return $this->m_key; }
}
