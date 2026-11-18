<?php

/*
 * What the mutable and readonly maps share: checking a map takes entries of its own entry class, keying them, and
 * creating an entry from a key and a value. This is internal to the package, and may change in any release.
 *
 * A map keeps its entries in a PHP array, by their keys. PHP treats a string key that's a whole number (e.g. "123")
 * as that number, so the two are the same key in a map. The keys a map gives out are read from its entries, so each
 * is given out as it was when its entry was created.
 */

namespace Programster\Collections\Internal;

use Programster\Collections\AbstractEntry;
use Programster\Collections\Exceptions\InvalidArgumentException;

/**
 * @internal
 */
trait MapTools
{
    use ElementTools;


    /**
     * Check a map's constructor takes entries of an entry class of its own: a class that extends AbstractEntry, and
     * isn't AbstractEntry itself, which would let entries with values of any type in. It's only checked once for each
     * class, as it can't change.
     *
     * @param class-string $class - the map's class.
     *
     * @return void
     *
     * @throws \LogicException - if it doesn't.
     */
    private static function assertValidEntryType(string $class): void
    {
        $type = self::readElementType($class);

        if (!is_subclass_of($type, AbstractEntry::class))
        {
            $problem = ($type === AbstractEntry::class)
                ? "AbstractEntry itself, which would let entries with values of any type in"
                : "\"{$type}\", which isn't an entry";

            throw new \LogicException(
                "{$class}'s constructor must take entries of an entry class of its own, one that extends AbstractEntry "
                . "(e.g. InvoiceEntry ...\$entries), but takes {$problem}."
            );
        }
    }


    /**
     * Check every entry is of the map's entry class, and key them by their keys.
     *
     * @param array<mixed> $entries - the entries, as the constructor received them. Any keys they were given (e.g. by
     * spreading an array) are ignored, as each entry has its own key.
     *
     * @return array<array-key, AbstractEntry<mixed>> - the entries, by their keys.
     *
     * @throws InvalidArgumentException - if an entry isn't of the map's entry class, or two have the same key.
     */
    private function keyEntries(array $entries): array
    {
        $keyed = [];

        foreach ($entries as $given)
        {
            $entry = $this->assertOwnEntry($given);
            $key = $entry->getKey();

            if (array_key_exists($key, $keyed))
            {
                throw new InvalidArgumentException(
                    "Two entries of a " . static::class . " have the key " . self::describeKey($key) . ", but each key "
                    . "can only be used once. (PHP treats a string that's a whole number, e.g. \"123\", as the same "
                    . "key as that number.) To replace an entry, use " . self::describeReplaceMethod() . "."
                );
            }

            $keyed[$key] = $entry;
        }

        return $keyed;
    }


    /**
     * Check something is an entry of the map's entry class. The methods that take one declare it as AbstractEntry, as
     * PHP doesn't let a map narrow it to its own entry class, so this checks it as the code runs, and PHPStan checks
     * it where they're called, through the map's generics.
     *
     * @param mixed $entry - the entry.
     *
     * @return AbstractEntry<mixed> - the entry, once checked.
     *
     * @throws InvalidArgumentException - if it isn't.
     */
    private function assertOwnEntry(mixed $entry): AbstractEntry
    {
        $type = $this->getType();

        if (!self::isOfType($entry, $type) || !($entry instanceof AbstractEntry))
        {
            throw new InvalidArgumentException(
                "A " . static::class . " can only hold entries of {$type}, but was given " . get_debug_type($entry)
                . "."
            );
        }

        return $entry;
    }


    /**
     * Create an entry of the map's entry class from a key and a value, for $map['key'] = $value. Its constructor has
     * been checked to take just those (see AbstractEntry), and PHP checks the value's type as it's created.
     *
     * @param int|string $key - the key.
     *
     * @param mixed $value - the value.
     *
     * @return AbstractEntry<mixed>
     *
     * @throws \TypeError - if the value isn't of the entry's type.
     */
    private function createEntry(int|string $key, mixed $value): AbstractEntry
    {
        $entryClass = $this->getType();
        $entry = new $entryClass($key, $value);

        if (!($entry instanceof AbstractEntry))
        {
            throw new \LogicException("{$entryClass} isn't an entry.");
        }

        return $entry;
    }


    /**
     * Get the keys of keyed entries, in order, each as it was when its entry was created.
     * @param array<array-key, AbstractEntry<mixed>> $entries - the entries, by their keys.
     * @return list<int|string>
     */
    private static function readKeys(array $entries): array
    {
        $keys = [];

        foreach ($entries as $entry)
        {
            $keys[] = $entry->getKey();
        }

        return $keys;
    }


    /**
     * Describe a key for a message: a string in quotes, or a number as it is.
     * @param int|string $key - the key.
     * @return string
     */
    private static function describeKey(int|string $key): string
    {
        return is_string($key) ? "\"{$key}\"" : (string)$key;
    }


    /**
     * The method to use to replace an entry, for messages: put() for a mutable map, with() for a readonly one.
     * @return string
     */
    private static function describeReplaceMethod(): string
    {
        return method_exists(static::class, 'put') ? "put()" : "with()";
    }
}
