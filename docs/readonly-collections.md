# Readonly collections

- [Creating a collection](#creating-a-collection)
- [Methods](#methods)
- [Reading elements](#reading-elements)
- [Sorting, filtering and combining](#sorting-filtering-and-combining)
- [Coming from ramsey/collection](#coming-from-ramseycollection)

A readonly collection can't be changed once it's created: nothing can add to it, remove from it or replace its
elements. Methods that would change it return a new collection instead, and leave the original as it was. Use one
wherever a collection doesn't need to change after it's created, which is most of the time.

## Creating a collection
Extend `AbstractReadonlyCollection` with a readonly class. Its constructor must take just the elements, as one typed
variadic parameter, and pass them on:

```php
final readonly class User
{
    public function __construct(public string $name, public int $age) {}

    public function isAdult(): bool { return $this->age >= 18; }
}

/**
 * @extends AbstractReadonlyCollection<User>
 */
final readonly class UserCollection extends AbstractReadonlyCollection
{
    public function __construct(User ...$users)
    {
        parent::__construct(...$users);
    }
}

$ann = new User("Ann", 40);
$bob = new User("Bob", 16);
$cat = new User("Cat", 33);
$users = new UserCollection($ann, $bob, $cat);
```

- **The typed variadic** makes PHP reject an element of the wrong type where the collection is created.
- **The constructor can't take anything else**, as the methods that return a new collection create it with just the
  elements. The base class checks this the first time each collection is created, and throws a `\LogicException` if it
  takes more.
- **The class must be readonly**, which PHP enforces, as a readonly class can only be extended by another.
- **The `@extends` docblock** tells PHPStan and your IDE the type of the elements, so they know what `first()`,
  `get()` and a `foreach` give you.
- **The elements' type is read from the constructor**, so there's nothing else to write. It can be a class, interface
  or enum, or one of the built-in types `array`, `bool`, `callable`, `float`, `int`, `object` or `string`. For
  elements of more than one class, declare an interface they share. A constructor that doesn't declare one type (none,
  `mixed`, a nullable type, or a union) throws a `\LogicException` the first time the collection is created. `getType()`
  returns the type, e.g. `"User"`.

The elements are always numbered from 0. Any keys, such as from spreading an associative array into the constructor,
are dropped.

## Methods

| Method | What it does |
|---|---|
| `get(int $index)` | Gets the element at a position, or throws `OutOfBoundsException`. |
| `first()` / `last()` | Gets the first or last element, or throws `NoSuchElementException` if it's empty. |
| `contains($element, bool $strict = true)` | Checks whether the collection holds an element. |
| `column(string $propertyOrMethod)` | Gets the value of a public property, method or array key from every element. |
| `sort(?string $propertyOrMethod = null, Sort $order = Sort::ASCENDING)` | Returns a new, sorted collection. |
| `filter(callable $callback)` | Returns a new collection of the elements the callback returns true for. |
| `where(?string $propertyOrMethod, mixed $value)` | Returns a new collection of the elements with that value. |
| `map(callable $callback)` | Returns an array of the callback's result for each element. |
| `reduce(callable $callback, mixed $initial)` | Reduces the elements to a single value. |
| `merge(self ...$collections)` | Returns a new collection with the others' elements added on the end. |
| `diff(self $other)` | Returns a new collection of the elements that are in only one of the two. |
| `intersect(self $other)` | Returns a new collection of the elements that are in both. |
| `toArray()` | Gets the elements as an array. Changing the array doesn't change the collection. |
| `isEmpty()`, `count()`, `getType()` | Check whether there are any elements, count them, or get their type. |

| `jsonSerialize()` | Gives a JSON array of the elements, so `json_encode($collection)` encodes them. |

A collection can also be looped over with `foreach`, and passed to `count()` and `json_encode()`.

## Reading elements
```php
$first = $users->get(0);         // Ann, by position.
$alsoFirst = $users->first();    // The same.
$last = $users->last();          // Cat.
$hasAnn = $users->contains($ann);       // true, as it's the same object.
$names = $users->column('name');        // ["Ann", "Bob", "Cat"], from a public property.
$adults = $users->column('isAdult');    // [true, false, true], from a public method.

foreach ($users as $index => $user)
{
    print "{$index}: {$user->name}" . PHP_EOL;
}
```

`column()`, `sort()` and `where()` read a public property, call a public method that takes no arguments, or, for a
collection of arrays, read a key. They throw an `InvalidPropertyOrMethod` if the elements have no such property, method
or key, and an `UnsupportedOperationException` if the elements are scalars, which have none of them:

```php
// Throws InvalidPropertyOrMethod
$emails = $users->column('email');
```

`contains()`, `diff()` and `intersect()` compare elements with `===`, so an object only matches itself, not an equal
copy of it. Pass `false` as `contains()`'s second argument to compare with `==` instead.

## Sorting, filtering and combining
```php
$byName = $users->sort('name');
$oldestFirst = $users->sort('age', Sort::DESCENDING);

$isAdult = function (User $user): bool {
    return $user->isAdult();
};

$grownUps = $users->filter($isAdult);
$fortyYearOlds = $users->where('age', 40);
$everyone = $users->merge(new UserCollection(new User("Dan", 50)));

print count($everyone) . " users, and still " . count($users) . " in the original." . PHP_EOL;
```

Each of these returns a new collection of the same class, and leaves `$users` as it was. `merge()`, `diff()` and
`intersect()` only accept a collection of the same class, which PHPStan checks as you type, and they throw a
`CollectionMismatchException` if given another.

## Coming from ramsey/collection
The collection is similar to ramsey/collection's, so it'll feel familiar, but it isn't compatible with it. It's built
around defensive programming, so in places it works differently, to catch mistakes as early as possible, and so that it
can't be changed:

- **There's no `add()`, `remove()` or `clear()`**, as they would change the collection. Use `merge()` to add elements,
  and `filter()` to leave some out.
- **Every collection must have a typed variadic constructor**, rather than being created from an array, so PHP checks
  the elements' type where the collection is made.
- **`Sort`'s cases are `Sort::ASCENDING` and `Sort::DESCENDING`.**
- **There's no array syntax** (`ArrayAccess`), such as `$users[] = $user` or `$users[0]`. Writing to a collection
  that way could only fail when the code runs, so it's left out, to be caught by PHP and your IDE as it's typed. Use
  `get()` to read an element by its position.
- **`map()` returns an array**, as its results can be of any type. Put them in a collection of their type if needed.
- **The elements are always numbered from 0.**

If a collection does need to change after it's created, use a [mutable collection](mutable-collections.md).
