<?php

/*
 * Smoke tests for AbstractReadonlyCollection. These are run by testing/main.php, which provides $smoke.
 */

use Programster\Collections\Exceptions\CollectionException;
use Programster\Collections\Exceptions\CollectionMismatchException;
use Programster\Collections\Exceptions\InvalidArgumentException;
use Programster\Collections\Exceptions\InvalidPropertyOrMethod;
use Programster\Collections\Exceptions\NoSuchElementException;
use Programster\Collections\Exceptions\OutOfBoundsException;
use Programster\Collections\Exceptions\UnsupportedOperationException;
use Programster\Collections\Sort;
use Programster\Collections\Testing\Fixtures\CollectionWithExtraParameter;
use Programster\Collections\Testing\Fixtures\MistypedReadonlyCollection;
use Programster\Collections\Testing\Fixtures\OtherUserCollection;
use Programster\Collections\Testing\Fixtures\ReadonlyStringCollection;
use Programster\Collections\Testing\Fixtures\UntypedUserCollection;
use Programster\Collections\Testing\Fixtures\User;
use Programster\Collections\Testing\Fixtures\UserCollection;
use Programster\Collections\Testing\SmokeTest;

/** @var \Programster\Collections\Testing\SmokeTest $smoke */

$ann = new User('Ann', 40);
$bob = new User('Bob', 25);
$cat = new User('Cat', 33);
$users = new UserCollection($bob, $ann, $cat);

$getNames = function (UserCollection $collection): array {
    return $collection->column('name');
};

$smoke->run("readonly: holds its elements in order, numbered from 0", function () use ($users, $ann, $bob, $cat): void {
    SmokeTest::assertSame([$bob, $ann, $cat], $users->toArray(), "elements");
    SmokeTest::assertSame(3, count($users), "count()");
    SmokeTest::assertSame(3, $users->count(), "->count()");
    SmokeTest::assertSame(false, $users->isEmpty(), "isEmpty()");
    SmokeTest::assertSame(true, (new UserCollection())->isEmpty(), "an empty collection");
    SmokeTest::assertSame(User::class, $users->getType(), "getType()");
    $keyed = new UserCollection(...['first' => $ann, 'second' => $bob]);
    SmokeTest::assertSame([0, 1], array_keys($keyed->toArray()), "keys from spreading an associative array");
});

$smoke->run("readonly: can be looped over", function () use ($users): void {
    $names = [];

    foreach ($users as $index => $user)
    {
        $names[$index] = $user->name;
    }

    SmokeTest::assertSame(['Bob', 'Ann', 'Cat'], $names, "names by index");
});

$smoke->run("readonly: get, first and last", function () use ($users, $ann, $bob, $cat): void {
    SmokeTest::assertSame($ann, $users->get(1), "get(1)");
    SmokeTest::assertSame($bob, $users->first(), "first()");
    SmokeTest::assertSame($cat, $users->last(), "last()");

    $getMissing = function () use ($users): void {
        $users->get(3);
    };

    SmokeTest::expectException(OutOfBoundsException::class, $getMissing, "index 3");

    $getFirstOfEmpty = function (): void {
        (new UserCollection())->first();
    };

    SmokeTest::expectException(NoSuchElementException::class, $getFirstOfEmpty);

    $getLastOfEmpty = function (): void {
        (new UserCollection())->last();
    };

    SmokeTest::expectException(NoSuchElementException::class, $getLastOfEmpty);
});

$smoke->run("readonly: contains", function () use ($users, $ann): void {
    SmokeTest::assertSame(true, $users->contains($ann), "the same object");
    SmokeTest::assertSame(false, $users->contains(new User('Ann', 40)), "an equal object, strictly");
    SmokeTest::assertSame(true, $users->contains(new User('Ann', 40), false), "an equal object, loosely");
});

$smoke->run("readonly: column reads public properties and methods", function () use ($users): void {
    SmokeTest::assertSame(['Bob', 'Ann', 'Cat'], $users->column('name'), "a property");
    SmokeTest::assertSame([25, 40, 33], $users->column('getAge'), "a method");

    $readMissing = function () use ($users): void {
        $users->column('email');
    };

    SmokeTest::expectException(InvalidPropertyOrMethod::class, $readMissing, "email");

    # A private property isn't readable, even though it exists.
    $readPrivate = function () use ($users): void {
        $users->column('m_age');
    };

    SmokeTest::expectException(InvalidPropertyOrMethod::class, $readPrivate, "m_age");
});

$smoke->run("readonly: sort returns a new, sorted collection", function () use ($users, $getNames): void {
    SmokeTest::assertSame(['Ann', 'Bob', 'Cat'], $getNames($users->sort('name')), "by a property");
    SmokeTest::assertSame(['Bob', 'Cat', 'Ann'], $getNames($users->sort('getAge')), "by a method");
    SmokeTest::assertSame(['Cat', 'Bob', 'Ann'], $getNames($users->sort('name', Sort::DESCENDING)), "descending");
    SmokeTest::assertSame(['Bob', 'Ann', 'Cat'], $getNames($users), "the original is unchanged");
    $strings = new ReadonlyStringCollection('b', 'c', 'a');
    SmokeTest::assertSame(['a', 'b', 'c'], $strings->sort()->toArray(), "by the elements themselves");
});

$smoke->run("readonly: filter and where return a new collection", function () use ($users, $getNames): void {
    $isOver30 = function (User $user): bool {
        return $user->getAge() > 30;
    };

    $over30 = $users->filter($isOver30);
    SmokeTest::assertSame(UserCollection::class, get_class($over30), "the class filter() returns");
    SmokeTest::assertSame(['Ann', 'Cat'], $getNames($over30), "filter()");
    SmokeTest::assertSame([0, 1], array_keys($over30->toArray()), "renumbered from 0");
    SmokeTest::assertSame(['Ann'], $getNames($users->where('getAge', 40)), "where() by a method");
    SmokeTest::assertSame(['Cat'], $getNames($users->where('name', 'Cat')), "where() by a property");
    SmokeTest::assertSame(3, count($users), "the original is unchanged");
});

$smoke->run("readonly: map and reduce", function () use ($users): void {
    $getInitial = function (User $user): string {
        return $user->name[0];
    };

    SmokeTest::assertSame(['B', 'A', 'C'], $users->map($getInitial), "map()");

    $addAge = function (int $total, User $user): int {
        return $total + $user->getAge();
    };

    SmokeTest::assertSame(98, $users->reduce($addAge, 0), "reduce()");
});

$smoke->run("readonly: merge, diff and intersect", function () use ($users, $ann, $getNames): void {
    $dan = new User('Dan', 50);
    $merged = $users->merge(new UserCollection($dan), new UserCollection($ann));
    SmokeTest::assertSame(['Bob', 'Ann', 'Cat', 'Dan', 'Ann'], $getNames($merged), "merge()");
    SmokeTest::assertSame(3, count($users), "the original is unchanged");
    $others = new UserCollection($ann, $dan);
    SmokeTest::assertSame(['Bob', 'Cat', 'Dan'], $getNames($users->diff($others)), "diff()");
    SmokeTest::assertSame(['Ann'], $getNames($users->intersect($others)), "intersect()");

    $mergeOtherClass = function () use ($users, $dan): void {
        $users->merge(new OtherUserCollection($dan));
    };

    SmokeTest::expectException(CollectionMismatchException::class, $mergeOtherClass, OtherUserCollection::class);

    $diffOtherClass = function () use ($users, $dan): void {
        $users->diff(new OtherUserCollection($dan));
    };

    SmokeTest::expectException(CollectionMismatchException::class, $diffOtherClass);
});

$smoke->run("readonly: scalar collections", function (): void {
    $strings = new ReadonlyStringCollection('x', 'y');
    SmokeTest::assertSame(['x', 'y'], $strings->toArray(), "elements");

    $readProperty = function () use ($strings): void {
        $strings->column('length');
    };

    SmokeTest::expectException(UnsupportedOperationException::class, $readProperty);
});

$smoke->run("readonly: rejects an element of the wrong type, even without a typed constructor", function (): void {
    $createWithString = function (): void {
        new UntypedUserCollection(new User('Ann', 40), 'not a user');
    };

    SmokeTest::expectException(InvalidArgumentException::class, $createWithString, "was given string");
});

$smoke->run("readonly: rejects a getType() that names a type that doesn't exist", function (): void {
    $createMistyped = function (): void {
        new MistypedReadonlyCollection(new User('Ann', 40));
    };

    SmokeTest::expectException(\LogicException::class, $createMistyped, "\"Usr\"");
});

$smoke->run("readonly: rejects a constructor that takes more than the elements", function (): void {
    $createWithLabel = function (): void {
        new CollectionWithExtraParameter('Staff', new User('Ann', 40));
    };

    SmokeTest::expectException(\LogicException::class, $createWithLabel, "must take just its elements");
});

$smoke->run("readonly: can't be changed", function () use ($users): void {
    # Even reflection, which can get past private and protected, can't change a readonly property.
    $writeProperty = function () use ($users): void {
        (new \ReflectionProperty($users, 'm_elements'))->setValue($users, []);
    };

    SmokeTest::expectException(\Error::class, $writeProperty, "readonly");

    $changeCopy = $users->toArray();
    $changeCopy[] = new User('Eve', 20);
    SmokeTest::assertSame(3, count($users), "changing toArray()'s result doesn't change the collection");
});

$smoke->run("readonly: every exception can be caught as a CollectionException", function (): void {
    $getFirstOfEmpty = function (): void {
        (new UserCollection())->first();
    };

    SmokeTest::expectException(CollectionException::class, $getFirstOfEmpty);
});
