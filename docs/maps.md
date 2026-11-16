# Maps

- [Creating a map](#creating-a-map)
- [Catching missing keys with PHPStan](#catching-missing-keys-with-phpstan)
- [Reading elements](#reading-elements)
- [Readonly maps: with() and without()](#readonly-maps-with-and-without)
- [Mutable maps](#mutable-maps)
- [Filtering, mapping and JSON](#filtering-mapping-and-json)
- [Coming from ramsey/collection](#coming-from-ramseycollection)

A map holds elements of one type, each looked up by a string key, e.g. users by their name. Like collections, there
are two kinds:

- **Readonly maps** (`AbstractReadonlyMap`) can't be changed once they're created. `with()` and `without()` return a
  new map instead, and leave the original as it was. Use one wherever a lookup table doesn't need to change. Array
  syntax can read one (`$users['ann']`), but writing to one with it throws.
- **Mutable maps** (`AbstractMap`) can be changed after they're created, with `put()`, `remove()` and `clear()`, or
  array syntax. Their elements are still checked as they're added.

## Creating a map
Extend `AbstractReadonlyMap` with a readonly class (or `AbstractMap` with a class). Its constructor must take just the
elements, as one typed variadic parameter, and pass them on. The elements' type is read from the constructor, as for a
[collection](readonly-collections.md#creating-a-collection):

```php
final readonly class User
{
    public function __construct(public string $name, public int $age) {}
}

/**
 * @extends AbstractReadonlyMap<User>
 */
final readonly class UserMap extends AbstractReadonlyMap
{
    public function __construct(User ...$users)
    {
        parent::__construct(...$users);
    }
}
```

Give it the elements by key, by spreading an associative array, or with named arguments:

```php
$ann = new User("Ann", 40);
$bob = new User("Bob", 25);

$users = new UserMap(...['ann' => $ann, 'bob' => $bob]);
$sameUsers = new UserMap(ann: $ann, bob: $bob);
```

The typed variadic means PHP and your IDE reject an element of the wrong type where the map is made. Keys are always
strings, as a variadic only keeps the keys of elements passed by name. An element passed by position would arrive
without its key, so it's rejected, rather than silently being given a number:

```php
// Throws InvalidArgumentException
$noKeys = new UserMap($ann, $bob);
```

The same goes for a key that's a whole number, such as `'123'`, as PHP turns it into a position when it's spread. Use
keys that aren't numbers (e.g. `'user-123'`), or a [collection](readonly-collections.md) if the elements are really a
list.

## Catching missing keys with PHPStan
PHP can't make a variadic be passed by name, so it can't reject `new UserMap($ann, $bob)` where it's written: only the
map's constructor can, when the code runs. The package comes with a PHPStan rule that reports it where it's written
instead, along with spreading an array whose keys might not all be strings (e.g. a `list<User>`, an `array` with no
key type, or a key like `'123'`), so the mistake shows up in PHPStan and in an IDE that runs it.

With [phpstan/extension-installer](https://github.com/phpstan/extension-installer), the rule is loaded automatically:

```bash
composer require --dev phpstan/extension-installer
```

Without it, include the package's `extension.neon` in your PHPStan configuration:

```neon
includes:
    - vendor/programster/collections/extension.neon
```

This also loads the package's rule that reports writing to a readonly map or collection with array syntax (see
[Readonly maps](#readonly-maps-with-and-without)). The rule for keys reports its errors with the identifier `collections.mapKeys`. To spread an array into a map, give it string keys, e.g.
`array<string, User>`, so PHPStan knows they're strings.

## Reading elements
```php
$bobAgain = $users->get('bob');           // Bob.
$alsoBob = $users['bob'];                 // The same, with array syntax.
$hasAnnKey = isset($users['ann']);        // true
$noCat = $users['cat'] ?? null;           // null, as there's no "cat".
$hasCat = $users->containsKey('cat');     // false.
$hasAnn = $users->contains($ann);         // true, as it's the same object.
$names = $users->keys();                  // ["ann", "bob"]
$list = $users->values();                 // [Ann, Bob], numbered from 0.
$array = $users->toArray();               // ["ann" => Ann, "bob" => Bob]

foreach ($users as $key => $user)
{
    print "{$key} is {$user->name}" . PHP_EOL;
}
```

`get()` and `$users['cat']` throw a `NoSuchElementException` for a key that isn't there, rather than returning
`null`, so check with `containsKey()`, `isset()` or `??` first if there might not be one:

```php
// Throws NoSuchElementException
$cat = $users->get('cat');
```

## Readonly maps: with() and without()
A readonly map can't be changed, so `with()` and `without()` return a new map, and leave the original as it was.
`with()` adds an element, or replaces the one already under that key (which keeps its place):

```php
$cat = new User("Cat", 33);
$withCat = $users->with('cat', $cat);     // ann, bob, cat
$withoutAnn = $withCat->without('ann');   // bob, cat
$stillTwo = count($users);                // 2, as $users hasn't changed.
```

`without()` throws a `NoSuchElementException` if the key isn't there, as removing it couldn't do anything, so it's
likely to be a mistake.

Writing to a readonly map with array syntax (`$users['cat'] = $cat` or `unset($users['ann'])`) throws an
`UnsupportedOperationException`, as it can't be changed. Use `with()` and `without()` instead:

```php
// Throws UnsupportedOperationException
$users['cat'] = $cat;
```

PHP can't refuse that where it's written, as reading and writing with array syntax come together (`ArrayAccess`), so
the package's PHPStan rules (see [above](#catching-missing-keys-with-phpstan)) report it there instead, with the
identifier `collections.readonlyWrite`.

## Mutable maps
A mutable map has the same methods for reading it, along with `put()`, `remove()` and `clear()`, and array syntax:

```php
/**
 * @extends AbstractMap<User>
 */
final class MutableUserMap extends AbstractMap
{
    public function __construct(User ...$users)
    {
        parent::__construct(...$users);
    }
}

$team = new MutableUserMap(ann: $ann);
$team->put('bob', $bob);
$team['cat'] = $cat;
$isBobThere = isset($team['bob']);        // true
$dan = $team['dan'] ?? null;              // null, as there's no "dan".
unset($team['ann']);
$team->remove('bob');                     // Only "cat" is left.
```

Array syntax works defensively: `$team[] = $user` throws an `InvalidArgumentException` (an element needs a key), and
reading or unsetting a key that isn't there throws a `NoSuchElementException`. `isset()` and `??` still work, as they
check first.

```php
// Throws InvalidArgumentException
$team[] = $ann;
```

## Filtering, mapping and JSON
`filter()` returns a new map (for both kinds, so a mutable map is left as it was), and `map()` returns an array, both
keeping the keys. Their callbacks are given each element and its key:

```php
$isOver30 = function (User $user, string $key): bool {
    return $user->age > 30;
};

$describe = function (User $user, string $key): string {
    return "{$key}: {$user->name}";
};

$over30 = $users->filter($isOver30);      // ann
$descriptions = $users->map($describe);   // ["ann" => "ann: Ann", "bob" => "bob: Bob"]
```

A map encodes as a JSON object of its elements, by key. An empty map is an empty object (`{}`), not an empty array, so
it's always the same kind of thing in the JSON:

```php
$json = json_encode($users);              // {"ann":{"name":"Ann","age":40},"bob":{"name":"Bob","age":25}}
$emptyJson = json_encode(new UserMap());  // {}
```

## Coming from ramsey/collection
The maps are similar to ramsey/collection's typed maps (`AbstractTypedMap`), but they aren't compatible with them, and
work differently in places, to catch mistakes as early as possible:

- **The elements' type is checked by PHP where the map is made**, through the constructor's typed variadic, rather
  than with `getValueType()` when the code runs. There's no `getKeyType()`, as keys are always strings.
- **An element without a key is rejected**, rather than being given a number.
- **`get()` and reading a missing key with array syntax throw**, rather than returning `null`. So does removing a key
  that isn't there.
- **A readonly map's array syntax only reads**, and writing with it throws (and is reported by the package's PHPStan
  rule). `with()` and `without()` return new maps instead.
- **`filter()` always returns a new map**, and `map()` returns an array, as the results can be of any type.
- **`keys()` and `values()`** are what ramsey/collection's maps call `keys()` and `toArray()`'s values.
