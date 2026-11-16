<?php

/*
 * Which kind of readonly class a value is, for ReadonlyWriteRule's messages. It's internal to the rule.
 */

declare(strict_types = 1);

namespace Programster\Collections\PHPStan;

/**
 * @internal
 */
enum ReadonlyKind
{
    case COLLECTION;
    case MAP;
    case NEITHER;


    /**
     * What it's called in a message, e.g. "collection".
     * @return string
     */
    public function describe(): string
    {
        return match ($this) {
            self::COLLECTION => "collection",
            self::MAP => "map",
            self::NEITHER => "class",
        };
    }


    /**
     * Say what to do instead of writing to one with array syntax.
     * @param bool $isUnset - whether the write was an unset().
     * @return string
     */
    public function describeAlternative(bool $isUnset): string
    {
        return match (true) {
            $this === self::COLLECTION && $isUnset => "Use filter() to get a new collection without the element.",
            $this === self::COLLECTION => "Use merge() to get a new collection with more elements.",
            $isUnset => "Use without() to get a new map without the element.",
            default => "Use with() to get a new map with the element.",
        };
    }
}
