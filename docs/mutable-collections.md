# Mutable collections

- [Creating a collection](#creating-a-collection)
- [Changing it](#changing-it)
- [Reading, sorting and combining](#reading-sorting-and-combining)
- [Coming from ramsey/collection](#coming-from-ramseycollection)

For a collection that genuinely needs to change after it's created, extend `AbstractCollection`. Prefer a
[readonly collection](readonly-collections.md) wherever the collection doesn't need to change.

## Creating a collection
Extend `AbstractCollection` with a class whose constructor takes the elements as a typed variadic, and passes them on,
so that PHP rejects an element of the wrong type where the collection is created, and whose `getType()` returns the
elements' type:

```php
final readonly class User
{
    public function __construct(public string $name, public int $age) {}
}

/**
 * @extends AbstractCollection<User>
 */
final class UserCollection extends AbstractCollection
{
    public function __construct(User ...$users)
    {
        parent::__construct(...$users);
    }

    public function getType(): string { return User::class; }
}

$users = new UserCollection(new User("Ann", 40), new User("Bob", 25));
```

- **The constructor is required.** The base class's constructor is protected, so a collection without its own can't
  be created, and every collection checks its elements' type where it's made.
- **Elements given string keys keep them**, such as by spreading an associative array into the constructor, e.g.
  `new UserCollection(...['owner' => $ann, 'admin' => $bob])`. The rest are numbered from 0.
- **`getType()`** returns a class, interface or enum name, or one of `"array"`, `"bool"` (or `"boolean"`),
  `"callable"`, `"float"` (or `"double"`), `"int"` (or `"integer"`), `"null"`, `"numeric"`, `"object"`, `"resource"`,
  `"scalar"`, `"string"` or `"mixed"`. It's checked the first time the collection is created, so a typo, or a class
  name that isn't imported, throws a `\LogicException` straight away, rather than every element being rejected.

## Changing it
```php
$users->add(new User("Cat", 33));        // On the end.
$users[] = new User("Dan", 50);          // The same.
$users['boss'] = new User("Eve", 60);    // With a key.
$boss = $users['boss'];
$hasBoss = isset($users['boss']);        // true.
unset($users['boss']);
$hasCat = $users->contains($boss);       // false, as it's been unset.
```

Every way of adding an element checks it's of the collection's type, and throws an `InvalidArgumentException` if not:

```php
// Throws InvalidArgumentException
$users[] = "not a user";
```

`remove()` removes the first occurrence of an element, and throws a `NoSuchElementException` if the collection doesn't
hold it, so a mistake can't go unnoticed. Use `contains()` first if it might not be there:

```php
// Throws NoSuchElementException
$users->remove($boss);
```

Reading a key that isn't there throws an `OutOfBoundsException`, and `clear()` removes every element.

## Reading, sorting and combining
The rest of the methods are the same as a [readonly collection's](readonly-collections.md#methods): `first()`,
`last()`, `contains()`, `column()`, `sort()`, `filter()`, `where()`, `map()`, `reduce()`, `merge()`, `diff()`,
`intersect()`, `toArray()`, `isEmpty()`, `count()` and `getType()`.

```php
$byName = $users->sort('name');

$isOver30 = function (User $user): bool {
    return $user->age > 30;
};

$over30 = $users->filter($isOver30);
print implode(", ", $over30->column('name')) . PHP_EOL;
```

`sort()`, `filter()`, `where()`, `merge()`, `diff()` and `intersect()` return a new collection, and leave the original
as it was. They copy the collection, so a constructor that takes more than the elements keeps its other values.
`sort()` and `filter()` number the elements from 0, while `merge()` keeps string keys, and adds the rest on the end.

Looping over a collection goes over a copy of its elements, so adding to it during the loop doesn't change what the
loop goes over. Unserializing a collection checks each element's type, so a changed serialized string can't put an
element of the wrong type in it.

## Coming from ramsey/collection
The collection is similar to ramsey/collection's `AbstractCollection`, so it'll feel familiar, but it isn't compatible
with it. It's built around defensive programming, so in places it works differently, to catch mistakes as early as
possible:

- **Every collection must have a typed variadic constructor**, rather than being created from an array, so PHP checks
  the elements' type where the collection is made.
- **`add()` returns nothing**, rather than always returning `true`.
- **`remove()` throws a `NoSuchElementException`** if the element isn't there, rather than returning `false`.
- **`getType()` is checked** the first time the collection is created.
- **`Sort`'s cases are `Sort::ASCENDING` and `Sort::DESCENDING`.**
- **`map()` returns an array**, as its results can be of any type, rather than a collection of mixed values.
- **Reading a key that isn't there throws an `OutOfBoundsException`**, rather than giving a warning and `null`.
- **Unserializing checks each element's type.**
