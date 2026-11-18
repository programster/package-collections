PHP Collections Package
=======================

[![Tests](https://github.com/programster/package-collections/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/programster/package-collections/actions/workflows/tests.yml)

## Contents
- [About](#about)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Readonly collections](docs/readonly-collections.md)
- [Mutable collections](docs/mutable-collections.md)
- [Maps](docs/maps.md)
- [Exceptions](docs/exceptions.md)
- [Upgrading](docs/upgrading.md)
- [Testing](docs/testing.md)
- [Design principles](#design-principles)
- [License](#license)

## About
Strictly typed collections for PHP, so that a collection can only ever hold the type of element it's meant to, and
your IDE and PHPStan know what's in it.

The collections are similar to [ramsey/collection](https://github.com/ramsey/collection)'s, so they'll feel familiar,
but they aren't compatible with it. They're built around defensive programming, so in places they work differently,
to catch mistakes as early as possible. There are two kinds:

- **Readonly collections** (`AbstractReadonlyCollection`) can't be changed once they're created: nothing can add to
  one, remove from it or replace its elements, so it's safe to hand to other code, or to keep one you were given,
  without copying it to protect it. Use these wherever a collection doesn't need to change.
- **Mutable collections** (`AbstractCollection`) can be changed after they're created, for when one genuinely needs to
  be. Their elements are still checked as they're added.

There are also typed **maps**, of values looked up by an int or string key (e.g. invoices by their customer's ID), in
the same two kinds: `AbstractReadonlyMap` and `AbstractMap`. A map holds entries of its own entry class, each a key and
a value, so PHP refuses a value without a key, or of the wrong type, where its entry is created. See
[Maps](docs/maps.md).

Collections and maps encode as JSON (`json_encode()`) as an array of their elements, or for a map, an object of them
by key.

## Requirements
PHP 8.2, 8.3, 8.4 or 8.5. It has no other dependencies.

## Installation
```bash
composer require programster/collections
```

If you use PHPStan, also install its extension installer, which loads the package's rule, so a write to a readonly
collection or map with array syntax (see [Array syntax](docs/readonly-collections.md#array-syntax)) is reported where
it's written:

```bash
composer require --dev phpstan/extension-installer
```

## Quick start
Create a collection for your type by extending `AbstractReadonlyCollection` with a readonly class, whose constructor
takes just the elements as a typed variadic, so that PHP rejects an element of the wrong type where it's created:

```php
<?php

use Programster\Collections\AbstractReadonlyCollection;
use Programster\Collections\Sort;

require_once(__DIR__ . '/vendor/autoload.php');

final readonly class User
{
    public function __construct(public string $name, public int $age) {}
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

$users = new UserCollection(
    new User("Programster", 40),
    new User("Bar", 25),
    new User("Foo", 33),
);

// Methods that would change a collection return a new one instead, and leave the original as it was.
$byName = $users->sort('name');
$oldestFirst = $users->sort('age', Sort::DESCENDING);
$withNewUser = $users->merge(new UserCollection(new User("Added User", 20)));

print "Sorted by name: " . implode(", ", $byName->column('name')) . PHP_EOL;
print "The oldest is " . $oldestFirst->first()->name . PHP_EOL;
print "There are now " . count($withNewUser) . " users, and still " . count($users) . " in the original." . PHP_EOL;
```

See [Readonly collections](docs/readonly-collections.md) for every method,
[Mutable collections](docs/mutable-collections.md) for a collection that can be changed after it's created, and
[Maps](docs/maps.md) for looking values up by a key.

## Design principles
- **Mistakes are caught as they're typed.** A collection's constructor takes a typed variadic, so PHP and your IDE
  reject an element of the wrong type where the collection is made. The type is only written there, and read from it,
  so it can't be written two different ways. A readonly collection can be read with array syntax (`$users[0]`), and
  the package's PHPStan rule reports any write to one with it where it's written, rather than when it throws as the
  code runs.
- **Readonly by default.** Use a readonly collection unless the collection genuinely needs to change after it's
  created.
- **Failures throw.** Methods throw a specific exception, rather than returning `null` or `false`. Every exception the
  package throws implements `CollectionException`, so you can catch them all at once.
- **No dependencies.** PHPStan is only needed for the package's optional rules.

## License
MIT. See [LICENSE](LICENSE).
