# Exceptions

Every exception a [readonly collection](readonly-collections.md) throws implements
`Programster\Collections\Exceptions\CollectionException`, so you can catch them all at once:

```php
use Programster\Collections\Exceptions\CollectionException;

try
{
    $firstUser = $users->first();
}
catch (CollectionException $e)
{
    // Handle any collection error.
}
```

They're all in the `Programster\Collections\Exceptions` namespace:

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidArgumentException` | `\InvalidArgumentException` | An element isn't of the collection's type, which can only happen if its constructor doesn't declare the type. |
| `OutOfBoundsException` | `\OutOfBoundsException` | `get()` is given a position the collection doesn't have. |
| `NoSuchElementException` | `\RuntimeException` | `first()` or `last()` is called on an empty collection. |
| `CollectionMismatchException` | `\RuntimeException` | `merge()`, `diff()` or `intersect()` is given a collection of a different class. |
| `InvalidPropertyOrMethod` | `\RuntimeException` | `column()`, `sort()` or `where()` is given a name that isn't a public property or method of the elements. |
| `UnsupportedOperationException` | `\RuntimeException` | `column()`, `sort()` or `where()` is given a name, but the elements are scalars. |

Separately, a collection whose constructor takes anything other than its elements throws a `\LogicException` the first
time it's created, as that's a mistake in the collection class, rather than something to catch.

The [mutable `AbstractCollection`](mutable-collections.md) throws a plain `\Exception` when it's given an element of the
wrong type.
