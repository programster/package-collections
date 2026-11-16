# Upgrading

- [From 2.0.0 to 2.1.0](#from-200-to-210)
- [From 1.2.0 to 2.0.0](#from-120-to-200)
    - [Changes you need to make](#changes-you-need-to-make)
    - [Changes in behaviour](#changes-in-behaviour)
    - [New features](#new-features)
- [From 1.1.0 to 1.2.0](#from-110-to-120)
- [From 1.0.0 to 1.1.0](#from-100-to-110)
- [From 0.1.0 to 1.0.0](#from-010-to-100)

What changed in each release, and what, if anything, you need to change in your code when upgrading.

## From 2.0.0 to 2.1.0
Nothing needs to change in your code, unless you relied on a collection encoding as JSON as an empty object.

- **New: typed maps.** `AbstractReadonlyMap` and `AbstractMap` hold elements looked up by a string key, checked by a
  typed variadic constructor like the collections. They can replace ramsey/collection's typed maps. See
  [Maps](maps.md).
- **Collections encode as JSON.** Both kinds of collection implement `JsonSerializable`, so `json_encode()` gives a
  JSON array of the elements. Before, a readonly collection silently encoded as an empty object (`{}`), as its elements
  are in a property that isn't public. `ArrayInterface` (and so `CollectionInterface`) now extends `JsonSerializable`,
  so a class of your own that implements either needs a `jsonSerialize()` method.

## From 1.2.0 to 2.0.0
2.0.0 rewrites the mutable `AbstractCollection`, so it has the same methods as readonly collections, along with the
ones for changing it, and follows the same defensive principles. Its methods are similar to ramsey/collection's, so
they'll feel familiar, but it isn't compatible with ramsey/collection, as in places it works differently, to catch
mistakes as early as possible.

### Changes you need to make
- **Mutable collections: pass the elements on to the parent constructor, and give the collection a `getType()`.** The
  element type is no longer the constructor's first argument, and the base constructor is protected, so every
  collection must declare one, taking its elements as a typed variadic:

  ```php
  // 1.x
  public function __construct(User ...$users)
  {
      parent::__construct(User::class, ...$users);
  }

  // 2.0.0
  public function __construct(User ...$users)
  {
      parent::__construct(...$users);
  }

  public function getType(): string { return User::class; }
  ```

- **Mutable collections: `filter()` returns a new collection, and leaves the original as it was.** In 1.x, it removed
  elements from the collection itself, so `$users->filter($callback);` on its own line now does nothing. Use the
  collection it returns: `$users = $users->filter($callback);`.
- **Mutable collections no longer extend PHP's `ArrayObject`**, as `ArrayObject::exchangeArray()` replaced the
  elements without checking their type. Its methods are replaced by:
  - `append($x)` and `appendMultiple($xs)`: `add($x)` (or `$collection[] = $x`), once for each element.
  - `getArrayCopy()`: `toArray()`.
  - `asort()`, `uasort()` and the other sorts: `sort()`, which returns a new, sorted collection.
  - `exchangeArray($xs)`: `clear()`, then `add()` each element, or create a new collection.
  - `instanceof \ArrayObject` checks: `instanceof CollectionInterface`.
- **Mutable collections: an element of the wrong type throws an `InvalidArgumentException`**, from
  `Programster\Collections\Exceptions`, rather than a plain `\Exception`. It still extends `\Exception`, so
  `catch (\Exception $e)` still catches it.
- **Both kinds: `Sort`'s cases are now `Sort::ASCENDING` and `Sort::DESCENDING`**, rather than `Sort::Ascending` and
  `Sort::Descending`, to match how the package's other enums are named.
- **Both kinds: `getType()` must name a type that exists.** It's now checked the first time each collection is created,
  and throws a `\LogicException` if it isn't a class, interface or enum that exists, or one of the type names listed in
  [Readonly collections](readonly-collections.md#creating-a-collection). In 1.2.0, a typo meant every element was
  rejected.

### Changes in behaviour
- **Reading a key that isn't there throws an `OutOfBoundsException`**, rather than giving a warning and `null`.
- **Looping over a mutable collection goes over a copy of its elements**, so adding to it during the loop doesn't
  change what the loop goes over.
- **Unserializing a mutable collection checks each element's type**, so a changed serialized string can't put an element
  of the wrong type in it.

### New features
- **The mutable collection has the readonly collection's methods**, and ones for changing it: `add()`, `remove()`
  (which throws if the element isn't there) and `clear()`, along with array syntax. See
  [Mutable collections](mutable-collections.md).
- **`CollectionInterface` and `ArrayInterface`**, which the mutable collection implements.
- **More element types, for both kinds of collection**: as well as a class, interface or enum, `getType()` can return
  `"array"`, `"bool"`, `"callable"`, `"float"`, `"int"`, `"null"`, `"numeric"`, `"object"`, `"resource"`, `"scalar"`,
  `"string"` or `"mixed"`. 1.2.0's readonly collections only allowed `"string"`, `"int"`, `"float"` and `"bool"`.
- **`column()`, `sort()` and `where()` can read an array's keys**, for a collection of arrays.

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
