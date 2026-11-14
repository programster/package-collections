# Mutable collections

For a collection that genuinely needs to change after it's created, extend `AbstractCollection`. It works like a
normal PHP array (it extends `ArrayObject`), but only accepts elements of its type. Prefer a
[readonly collection](readonly-collections.md) wherever the collection doesn't need to change.

```php
use Programster\Collections\AbstractCollection;

/**
 * @extends AbstractCollection<User>
 */
class MutableUserCollection extends AbstractCollection
{
    public function __construct(User ...$users)
    {
        parent::__construct(User::class, ...$users);
    }
}

$users = new MutableUserCollection(new User("Programster", 40));

// Can append a user, like with a normal array.
$users[] = new User("Added User", 20);
$users->append(new User("Another User", 30));
$users->appendMultiple([new User("Third", 50), new User("Fourth", 60)]);
```

- **Adding an element of the wrong type throws an `\Exception`**, whether by `$users[] = ...`, `append()`,
  `appendMultiple()` or setting an index.
- **`filter(callable $callback)` changes the collection itself**, removing each element the callback returns `false`
  for, and returns the same collection. The remaining elements keep their indexes, so there can be gaps.
- **Every other `ArrayObject` method is available**, such as `count()`, `getArrayCopy()` and `uasort()`.
