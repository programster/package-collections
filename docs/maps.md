# Maps

- [Creating a map](#creating-a-map)
- [Keys](#keys)
- [Reading values](#reading-values)
- [Readonly maps: with() and without()](#readonly-maps-with-and-without)
- [Mutable maps](#mutable-maps)
- [Filtering, mapping and JSON](#filtering-mapping-and-json)
- [Mistakes that are refused](#mistakes-that-are-refused)
- [Coming from ramsey/collection](#coming-from-ramseycollection)

A map holds values of one type, each looked up by a key, e.g. invoices by their customer's ID. Like collections, there
are two kinds:

- **Readonly maps** (`AbstractReadonlyMap`) can't be changed once they're created. `with()` and `without()` return a
  new map instead, and leave the original as it was. Use one wherever a lookup table doesn't need to change. Array
  syntax can read one (`$invoices[12]`), but writing to one with it throws.
- **Mutable maps** (`AbstractMap`) can be changed after they're created, with `put()`, `remove()` and `clear()`, or
  array syntax.

A map holds **entries**, each a key and a value, of an entry class of its own. PHP checks every entry where it's
created: it can't be made without a key, and its value must be of the entry's type. So a value can never be put in a
map without a key, or with the wrong type.

## Creating a map
A map needs two small classes: an entry class, and the map itself.

The entry class extends `AbstractEntry` with a readonly class. Its constructor takes the key, declared as
`int|string` (or just `int`, or just `string`), and then the value, declared as its one type, and passes them on. The
value's type is read from the constructor, so it's only written there:

```php
final readonly class Invoice
{
    public function __construct(public string $number, public int $pence) {}
}

/**
 * @extends AbstractEntry<Invoice>
 */
final readonly class InvoiceEntry extends AbstractEntry
{
    public function __construct(int|string $key, Invoice $invoice)
    {
        parent::__construct($key, $invoice);
    }
}
```

The map extends `AbstractReadonlyMap` with a readonly class (or `AbstractMap` with a class). Its constructor takes
just the entries, as a typed variadic of its entry class, and passes them on:

```php
/**
 * @extends AbstractReadonlyMap<Invoice, InvoiceEntry>
 */
final readonly class InvoiceMap extends AbstractReadonlyMap
{
    public function __construct(InvoiceEntry ...$entries)
    {
        parent::__construct(...$entries);
    }
}

$first = new Invoice("INV-1", 1500);
$second = new Invoice("INV-2", 2500);

// Invoices by the ID of the customer they're for.
$invoices = new InvoiceMap(new InvoiceEntry(12, $first), new InvoiceEntry(15, $second));
```

The `@extends` lines tell PHPStan and your IDE the value's type, and the map's entry class, so they know what
`get()` returns, and report an entry of another class given to `with()` or `put()`.

An entry class can have creators of its own, which are a good place to say how two objects are tied together, without
either of them having to know about the other:

```php
final readonly class Customer
{
    public function __construct(public int $id, public string $name) {}
}

/**
 * @extends AbstractEntry<Invoice>
 */
final readonly class CustomerInvoiceEntry extends AbstractEntry
{
    public function __construct(int|string $key, Invoice $invoice)
    {
        parent::__construct($key, $invoice);
    }


    public static function createForCustomer(Customer $customer, Invoice $invoice): CustomerInvoiceEntry
    {
        return new CustomerInvoiceEntry($customer->id, $invoice);
    }
}

$ann = new Customer(20, "Ann");
$annsEntry = CustomerInvoiceEntry::createForCustomer($ann, $first);
$annsKey = $annsEntry->getKey();          // 20
$annsInvoice = $annsEntry->getValue();    // INV-1
```

## Keys
A key is an int or a string, and each key can only be used once in a map. Keys of 0, 1, 2 and so on are keys like any
other, as each entry carries its own key, so a map never mistakes them for a list.

`keys()`, and the keys `foreach` gives, are each given back as they were when their entry was created. PHP treats a
string that's a whole number (e.g. `"123"`) as the same key as that number, as it does in any array, so a map can't
hold both, and `get("123")` and `get(123)` find the same value:

```php
$numbered = new InvoiceMap(new InvoiceEntry("123", $first));
$keys = $numbered->keys();                // ["123"], still a string.
$same = $numbered->get(123);              // INV-1, as PHP treats "123" and 123 as one key.
```

```php
// Throws InvalidArgumentException
$clash = new InvoiceMap(new InvoiceEntry("123", $first), new InvoiceEntry(123, $second));
```

## Reading values
Reading a map works with keys and values. You already have the key you're reading, so `get()` gives the value:

```php
$invoice = $invoices->get(12);            // INV-1
$alsoInvoice = $invoices[12];             // The same, with array syntax.
$hasIt = isset($invoices[15]);            // true
$none = $invoices[99] ?? null;            // null, as there's nothing under 99.
$hasKey = $invoices->containsKey(99);     // false
$hasValue = $invoices->contains($first);  // true, as it's the same object.
$keys = $invoices->keys();                // [12, 15]
$values = $invoices->values();            // [INV-1, INV-2], numbered from 0.
$array = $invoices->toArray();            // [12 => INV-1, 15 => INV-2]

foreach ($invoices as $customerId => $invoice)
{
    print "Customer {$customerId}'s invoice is {$invoice->number}" . PHP_EOL;
}
```

`get()` and `$invoices[99]` throw a `NoSuchElementException` for a key that isn't there, rather than returning
`null`, so check with `containsKey()`, `isset()` or `??` first if there might not be one:

```php
// Throws NoSuchElementException
$missing = $invoices->get(99);
```

`getEntries()` gives the entries themselves, in order, e.g. to create another map from them:

```php
$copy = new InvoiceMap(...$invoices->getEntries());
```

## Readonly maps: with() and without()
A readonly map can't be changed, so `with()` and `without()` return a new map, and leave the original as it was.
`with()` takes an entry, and adds it, or replaces the entry already under its key (which keeps its place):

```php
$third = new Invoice("INV-3", 900);
$withThird = $invoices->with(new InvoiceEntry(17, $third));   // 12, 15, 17
$withoutTwelve = $withThird->without(12);                       // 15, 17
$stillTwo = count($invoices);                                   // 2, as $invoices hasn't changed.
```

`with()` only takes entries of the map's own entry class, and `without()` throws a `NoSuchElementException` if the
key isn't there, as removing it couldn't do anything, so it's likely to be a mistake:

```php
// Throws NoSuchElementException
$invoices->without(99);
```

Writing to a readonly map with array syntax (`$invoices[17] = $third` or `unset($invoices[12])`) throws an
`UnsupportedOperationException`, as it can't be changed. Use `with()` and `without()` instead:

```php
// Throws UnsupportedOperationException
$invoices[17] = $third;
```

PHP can't refuse that where it's written, as reading and writing with array syntax come together (`ArrayAccess`), so
the package's PHPStan rule reports it there instead, with the identifier `collections.readonlyWrite`. With
[phpstan/extension-installer](https://github.com/phpstan/extension-installer), the rule is loaded automatically.
Without it, include the package's `extension.neon` in your PHPStan configuration:

```neon
includes:
    - vendor/programster/collections/extension.neon
```

## Mutable maps
A mutable map has the same methods for reading it, along with `put()` (which takes an entry), `remove()` and
`clear()`, and array syntax:

```php
/**
 * @extends AbstractMap<Invoice, InvoiceEntry>
 */
final class MutableInvoiceMap extends AbstractMap
{
    public function __construct(InvoiceEntry ...$entries)
    {
        parent::__construct(...$entries);
    }
}

$open = new MutableInvoiceMap(new InvoiceEntry(12, $first));
$open->put(new InvoiceEntry(15, $second));
$open[17] = $third;                       // Creates an InvoiceEntry for it, so PHP checks its type.
$open[] = new InvoiceEntry(19, $third);   // Adds a whole entry, under its own key.
$isThere = isset($open[15]);              // true
unset($open[12]);
$open->remove(15);                        // 17 and 19 are left.
```

`$open[17] = $third` creates an entry of the map's entry class, so a value of the wrong type is a `TypeError` on that
line. `$open[] = ...` only takes a whole entry, as a value on its own has no key:

```php
// Throws InvalidArgumentException
$open[] = $third;
```

## Filtering, mapping and JSON
`filter()` returns a new map (for both kinds, so a mutable map is left as it was), and `map()` returns an array, both
keeping the keys. Their callbacks are given each value and its key:

```php
$isLarge = function (Invoice $invoice, int|string $customerId): bool {
    return $invoice->pence > 2000;
};

$describe = function (Invoice $invoice, int|string $customerId): string {
    return "{$customerId}: {$invoice->number}";
};

$large = $invoices->filter($isLarge);          // 15
$descriptions = $invoices->map($describe);     // [12 => "12: INV-1", 15 => "15: INV-2"]
```

A map encodes as a JSON object of its values, by key. It's always an object, even when it's empty (`{}`), or its keys
are 0, 1, 2 and so on, which `json_encode()` would otherwise give as an array:

```php
$json = json_encode($invoices);                // {"12":{"number":"INV-1","pence":1500},"15":{...}}
$emptyJson = json_encode(new InvoiceMap());    // {}
```

## Mistakes that are refused
Each mistake is refused where it's made, or the first time a class written wrong is used:

- **A value without a key, or of the wrong type**, can't be made into an entry: PHP throws a `TypeError` on the line
  that creates it.
- **Two entries with the same key** throw an `InvalidArgumentException`, naming the key. To replace an entry, use
  `with()` or `put()`.
- **An entry of another class** given to `with()` or `put()` throws an `InvalidArgumentException`, and PHPStan reports
  it where it's passed.
- **An entry class written wrong** throws a `\LogicException` the first time it's created: its constructor must take
  just the key (`int|string`, `int` or `string`) and then the value, declared as one type that isn't nullable.
- **A map written wrong** throws a `\LogicException` the first time it's created: its constructor must take just its
  entries, as a typed variadic of an entry class of its own. Taking `AbstractEntry ...$entries` is refused, as it would
  let values of any type in.

## Coming from ramsey/collection
The maps are similar to ramsey/collection's typed maps (`AbstractTypedMap`), but they aren't compatible with them, and
work differently in places, to catch mistakes as early as possible:

- **A map holds entries, each a key and a value**, so PHP checks the value's type, and that it has a key, where each
  entry is made, rather than with `getKeyType()` and `getValueType()` when the code runs.
- **`get()` and reading a missing key with array syntax throw**, rather than returning `null`. So does removing a key
  that isn't there.
- **A readonly map's array syntax only reads**, and writing with it throws (and is reported by the package's PHPStan
  rule). `with()` and `without()` return new maps instead.
- **`filter()` always returns a new map**, and `map()` returns an array, as the results can be of any type.
- **`keys()` and `values()`** are what ramsey/collection's maps call `keys()` and `toArray()`'s values.
