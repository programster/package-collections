# Readonly collections

- [Creating a collection](#creating-a-collection)
- [Methods](#methods)
- [Reading elements](#reading-elements)
- [Sorting, filtering and combining](#sorting-filtering-and-combining)
- [Compared with ramsey/collection](#compared-with-ramseycollection)

A readonly collection can't be changed once it's created: nothing can add to it, remove from it or replace its
elements. Methods that would change it return a new collection instead, and leave the original as it was.

## Creating a collection
Extend `AbstractReadonlyCollection` with a readonly class. Its constructor must take just the elements, as one typed
variadic parameter, and pass them on, and its `getType()` must return the elements' type:

```php
use Programster\Collections\AbstractReadonlyCollection;

/**
 * @extends AbstractReadonlyCollection<User>
 */
final readonly class UserCollection extends AbstractReadonlyCollection
{
    public function __construct(User ...$users)
    {
        parent::__construct(...$users);
    }

    public function getType(): string { return User::class; }
}

$users = new UserCollection($ann, $bob);
```

- **The typed variadic** makes PHP reject an element of the wrong type where the collection is created. If a
  constructor leaves the type off, the base class still checks each element against `getType()`, and throws an
  `InvalidArgumentException`.
- **The constructor can't take anything else**, as the methods that return a new collection create it with just the
  elements. The base class checks this the first time each collection is created, and throws a `\LogicException` if it
  takes more.
- **The class must be readonly**, which PHP enforces, as a readonly class can only be extended by another.
- **The `@extends` docblock** tells PHPStan and your IDE the type of the elements, so they know what `first()`,
  `get()` and a `foreach` give you.
- **`getType()`** returns a class, interface or enum name, or one of `"string"`, `"int"`, `"float"` or `"bool"` for a
  collection of scalars.

The elements are always numbered from 0. Any keys, such as from spreading an associative array into the constructor,
are dropped.

## Methods

| Method | What it does |
|---|---|
| `get(int $index)` | Gets the element at a position, or throws `OutOfBoundsException`. |
| `first()` / `last()` | Gets the first or last element, or throws `NoSuchElementException` if it's empty. |
| `contains($element, bool $strict = true)` | Checks whether the collection holds an element. |
| `column(string $propertyOrMethod)` | Gets the value of a public property or method from every element. |
| `sort(?string $propertyOrMethod = null, Sort $order = Sort::Ascending)` | Returns a new, sorted collection. |
| `filter(callable $callback)` | Returns a new collection of the elements the callback returns true for. |
| `where(?string $propertyOrMethod, mixed $value)` | Returns a new collection of the elements with that value. |
| `map(callable $callback)` | Returns an array of the callback's result for each element. |
| `reduce(callable $callback, mixed $initial)` | Reduces the elements to a single value. |
| `merge(self ...$collections)` | Returns a new collection with the others' elements added on the end. |
| `diff(self $other)` | Returns a new collection of the elements that are in only one of the two. |
| `intersect(self $other)` | Returns a new collection of the elements that are in both. |
| `toArray()` | Gets the elements as an array. Changing the array doesn't change the collection. |
| `isEmpty()`, `count()`, `getType()` | Check whether there are any elements, count them, or get their type. |

A collection can also be looped over with `foreach`, and passed to `count()`.

## Reading elements
```php
$users->get(0);                  // The first user, by position.
$users->first();                 // The same.
$users->last();                  // The last user.
$users->contains($ann);          // Whether $ann is in it, as the same object.
$users->column('name');          // e.g. ["Ann", "Bob"], from a public property.
$users->column('getEmail');      // From a public method, which must take no arguments.

foreach ($users as $index => $user)
{
    print "{$index}: {$user->name}" . PHP_EOL;
}
```

`column()`, `sort()` and `where()` read a public property, or call a public method that takes no arguments. They
throw an `InvalidPropertyOrMethod` if the elements have no such public property or method, and an
`UnsupportedOperationException` if the elements are scalars, which have neither.

`contains()`, `diff()` and `intersect()` compare elements with `===`, so an object only matches itself, not an equal
copy of it. Pass `false` as `contains()`'s second argument to compare with `==` instead.

## Sorting, filtering and combining
```php
use Programster\Collections\Sort;

$byName = $users->sort('name');
$oldestFirst = $users->sort('getAge', Sort::Descending);

$isAdult = function (User $user): bool {
    return $user->getAge() >= 18;
};

$adults = $users->filter($isAdult);
$admins = $users->where('role', Role::ADMIN);
$everyone = $users->merge($moreUsers, $evenMoreUsers);
```

Each of these returns a new collection of the same class, and leaves `$users` as it was. `merge()`, `diff()` and
`intersect()` only accept a collection of the same class, which PHPStan checks as you type, and they throw a
`CollectionMismatchException` if given another.

## Compared with ramsey/collection
The methods have the same names, parameters and behaviour as ramsey/collection's wherever possible, so code written
for it can switch with few changes. The differences all come from the collection being readonly:

- **There's no `add()`, `remove()` or `clear()`**, as they would change the collection. Use `merge()` to add elements,
  and `filter()` to leave some out.
- **There's no array syntax** (`ArrayAccess`), such as `$users[] = $user` or `$users[0]`. Writing to a collection
  that way could only fail when the code runs, so it's left out, to be caught by PHP and your IDE as it's typed. Use
  `get()` to read an element by its position.
- **`map()` returns an array**, as its results can be of any type. Put them in a collection of their type if needed.
- **The elements are always numbered from 0.**
