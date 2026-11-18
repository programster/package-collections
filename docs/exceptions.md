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
| `InvalidArgumentException` | `\InvalidArgumentException` | An element isn't of the collection's type, or a mutable collection is given a key that isn't an int or string. A map is given an entry of another entry class, two entries with the same key, or a mutable map is given `$map[] = $value` with a value rather than an entry. |
| `OutOfBoundsException` | `\OutOfBoundsException` | An element is asked for by a position or key the collection doesn't have. |
| `NoSuchElementException` | `\RuntimeException` | `first()` or `last()` is called on an empty collection, or `remove()` is given an element the collection doesn't hold. A map is asked for, or to remove, a key it doesn't have. |
| `CollectionMismatchException` | `\RuntimeException` | `merge()`, `diff()` or `intersect()` is given a collection of a different class. |
| `InvalidPropertyOrMethod` | `\RuntimeException` | `column()`, `sort()` or `where()` is given a name that isn't a public property, method or key of the elements. |
| `UnsupportedOperationException` | `\RuntimeException` | `column()`, `sort()` or `where()` is given a name, but the elements are scalars, or a readonly collection or map is written to with array syntax. |

Separately, a collection or map whose constructor takes anything other than its elements, or doesn't declare their one
type, throws a `\LogicException` the first time it's created, as that's a mistake in the class, rather than something
to catch. So does a map whose constructor doesn't take entries of an entry class of its own, and an entry class whose
constructor doesn't take just a key and one typed value. A value of the wrong type given to an entry's constructor (or
to a mutable map with `$map['key'] = $value`) is a `\TypeError`, from PHP itself.
