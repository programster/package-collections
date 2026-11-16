# Exceptions

Every exception the collections throw implements `Programster\Collections\Exceptions\CollectionException`, so you can
catch them all at once:

```php
/**
 * @extends AbstractReadonlyCollection<string>
 */
final readonly class NameCollection extends AbstractReadonlyCollection
{
    public function __construct(string ...$names)
    {
        parent::__construct(...$names);
    }
}

$names = new NameCollection();

try
{
    $firstName = $names->first();
}
catch (CollectionException $e)
{
    print "Couldn't get the first name: " . $e->getMessage() . PHP_EOL;
}
```

They're all in the `Programster\Collections\Exceptions` namespace:

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidArgumentException` | `\InvalidArgumentException` | An element isn't of the collection's type, or a mutable collection is given a key that isn't an int or string. |
| `OutOfBoundsException` | `\OutOfBoundsException` | An element is asked for by a position or key the collection doesn't have. |
| `NoSuchElementException` | `\RuntimeException` | `first()` or `last()` is called on an empty collection, or `remove()` is given an element the collection doesn't hold. |
| `CollectionMismatchException` | `\RuntimeException` | `merge()`, `diff()` or `intersect()` is given a collection of a different class. |
| `InvalidPropertyOrMethod` | `\RuntimeException` | `column()`, `sort()` or `where()` is given a name that isn't a public property, method or key of the elements. |
| `UnsupportedOperationException` | `\RuntimeException` | `column()`, `sort()` or `where()` is given a name, but the elements are scalars, or a readonly collection or map is written to with array syntax. |

Separately, a collection or map whose constructor takes anything other than its elements, or doesn't declare their one
type, throws a `\LogicException` the first time it's created, as that's a mistake in the class, rather than something
to catch.
