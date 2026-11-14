# Upgrading

- [From 1.1.0 to 1.2.0](#from-110-to-120)
- [From 1.0.0 to 1.1.0](#from-100-to-110)
- [From 0.1.0 to 1.0.0](#from-010-to-100)

What changed in each release, and what, if anything, you need to change in your code when upgrading.

## From 1.1.0 to 1.2.0
Nothing needs to change in your code.

- **New: readonly collections.** `AbstractReadonlyCollection` is a typed collection that can't be changed once it's
  created, with the same interface as ramsey/collection wherever possible. See
  [Readonly collections](readonly-collections.md). It comes with the `Sort` enum, and the exceptions in
  `Programster\Collections\Exceptions` (see [Exceptions](exceptions.md)).
- **Fixed: `AbstractCollection::filter()` could keep elements it should have removed.** When two elements in a row
  were to be removed, it skipped the second, so it stayed in the collection. It now removes every element the callback
  returns `false` for.
- **`AbstractCollection` now has generic docblocks**, so PHPStan and your IDE know the type of its elements. Add
  `@extends AbstractCollection<YourType>` to your collection classes to use them.
- **PHP 9 isn't claimed until it's been tested.** The package requires `^8.2` (any PHP 8 release from 8.2), rather
  than `>=8.2.0`, which would have allowed it to be installed on PHP 9 before anyone knew if it worked there.

## From 1.0.0 to 1.1.0
- **PHP 8.2 or newer is required again**, as 1.0.0's support for PHP 7.4 was reverted.
- **`offsetSet()`'s parameters were renamed** from `$index` and `$newVal` to `$key` and `$value`, to match PHP's
  own `ArrayObject`. This only matters if you call it with named arguments.

## From 0.1.0 to 1.0.0
- **PHP 7.4 was supported**, so the `mixed` types were removed from `append()` and `offsetSet()`, and the `static`
  return type from `filter()`.
