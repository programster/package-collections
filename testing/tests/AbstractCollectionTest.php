<?php

/*
 * Smoke tests for the mutable AbstractCollection. These are run by testing/main.php, which provides $smoke.
 */

use Programster\Collections\CollectionInterface;
use Programster\Collections\Exceptions\CollectionException;
use Programster\Collections\Exceptions\CollectionMismatchException;
use Programster\Collections\Exceptions\InvalidArgumentException;
use Programster\Collections\Exceptions\InvalidPropertyOrMethod;
use Programster\Collections\Exceptions\NoSuchElementException;
use Programster\Collections\Exceptions\OutOfBoundsException;
use Programster\Collections\Exceptions\UnsupportedOperationException;
use Programster\Collections\Sort;
use Programster\Collections\Testing\Fixtures\CollectionWithoutConstructor;
use Programster\Collections\Testing\Fixtures\LabelledUserCollection;
use Programster\Collections\Testing\Fixtures\MutableArrayCollection;
use Programster\Collections\Testing\Fixtures\MutableIntCollection;
use Programster\Collections\Testing\Fixtures\NoTypeMutableCollection;
use Programster\Collections\Testing\Fixtures\MutableUserCollection;
use Programster\Collections\Testing\Fixtures\User;
use Programster\Collections\Testing\SmokeTest;

/** @var \Programster\Collections\Testing\SmokeTest $smoke */

$ann = new User('Ann', 40);
$bob = new User('Bob', 25);
$cat = new User('Cat', 33);

$makeUsers = function () use ($ann, $bob, $cat): MutableUserCollection {
    return new MutableUserCollection($bob, $ann, $cat);
};

$getNames = function (CollectionInterface $collection): array {
    return $collection->column('name');
};

$smoke->run("mutable: created from a typed variadic", function () use ($makeUsers, $ann, $bob): void {
    $users = $makeUsers();
    SmokeTest::assertSame(3, count($users), "count()");
    SmokeTest::assertSame(false, $users->isEmpty(), "isEmpty()");
    SmokeTest::assertSame(User::class, $users->getType(), "getType()");
    SmokeTest::assertSame(true, (new MutableUserCollection())->isEmpty(), "an empty collection");
    $keyed = new MutableUserCollection(...['owner' => $ann, 'admin' => $bob]);
    SmokeTest::assertSame(['owner' => $ann, 'admin' => $bob], $keyed->toArray(), "string keys are kept");
});

$smoke->run("mutable: a collection must have a typed constructor, which its type is read from", function (): void {
    $createWithoutConstructor = function (): void {
        new CollectionWithoutConstructor();
    };

    SmokeTest::expectException(\Error::class, $createWithoutConstructor, "protected");
    SmokeTest::assertSame('int', (new MutableIntCollection())->getType(), "a built-in type");

    $createUntyped = function (): void {
        new NoTypeMutableCollection(new User('Ann', 40));
    };

    SmokeTest::expectException(\LogicException::class, $createUntyped, "must declare the one type");
    SmokeTest::assertSame(User::class, (new MutableUserCollection())->getType(), "a class");
});

$smoke->run("mutable: rejects elements of the wrong type, however they're added", function () use ($makeUsers): void {
    $users = $makeUsers();

    $createWithString = function (): void {
        new MutableUserCollection(...['not a user']);
    };

    SmokeTest::expectException(\TypeError::class, $createWithString);

    $addString = function () use ($users): void {
        $users->add('not a user');
    };

    SmokeTest::expectException(InvalidArgumentException::class, $addString, "was given string");

    $appendString = function () use ($users): void {
        $users[] = 'not a user';
    };

    SmokeTest::expectException(InvalidArgumentException::class, $appendString);

    $setString = function () use ($users): void {
        $users['key'] = 'not a user';
    };

    SmokeTest::expectException(InvalidArgumentException::class, $setString);

    $useObjectKey = function () use ($users): void {
        $users[new \stdClass()] = new User('Dan', 50);
    };

    SmokeTest::expectException(InvalidArgumentException::class, $useObjectKey, "keys must be ints or strings");

    $unsetObjectKey = function () use ($users): void {
        unset($users[new \stdClass()]);
    };

    SmokeTest::expectException(InvalidArgumentException::class, $unsetObjectKey, "keys must be ints or strings");
    SmokeTest::assertSame(3, count($users), "nothing was added or removed");
});

$smoke->run("mutable: add, remove, clear and array syntax", function () use ($makeUsers, $ann, $bob): void {
    $users = $makeUsers();
    $dan = new User('Dan', 50);
    $users->add($dan);
    SmokeTest::assertSame($dan, $users->last(), "add() puts it on the end");
    $users->remove($ann);
    SmokeTest::assertSame(false, $users->contains($ann), "remove()");

    $removeAgain = function () use ($users, $ann): void {
        $users->remove($ann);
    };

    SmokeTest::expectException(NoSuchElementException::class, $removeAgain, "doesn't hold it");
    $users[] = $ann;
    SmokeTest::assertSame($ann, $users->last(), "\$users[] = ...");
    $users['boss'] = $bob;
    SmokeTest::assertSame($bob, $users['boss'], "\$users['boss']");
    # isset() calls offsetExists().
    SmokeTest::assertSame(true, $users->offsetExists('boss'), "offsetExists()");
    unset($users['boss']);
    SmokeTest::assertSame(false, $users->offsetExists('boss'), "unset()");
    $users->clear();
    SmokeTest::assertSame(true, $users->isEmpty(), "clear()");

    $getMissing = function () use ($users): void {
        $users['missing'];
    };

    SmokeTest::expectException(OutOfBoundsException::class, $getMissing, "missing");
});

$smoke->run("mutable: first, last, contains and column", function () use ($makeUsers, $ann, $bob, $cat): void {
    $users = $makeUsers();
    SmokeTest::assertSame($bob, $users->first(), "first()");
    SmokeTest::assertSame($cat, $users->last(), "last()");
    SmokeTest::assertSame(true, $users->contains($ann), "contains() the same object");
    SmokeTest::assertSame(false, $users->contains(new User('Ann', 40)), "contains() an equal object, strictly");
    SmokeTest::assertSame(true, $users->contains(new User('Ann', 40), false), "contains() an equal object, loosely");
    SmokeTest::assertSame([25, 40, 33], $users->column('getAge'), "column() by a method");

    $getFirstOfEmpty = function (): void {
        (new MutableUserCollection())->first();
    };

    SmokeTest::expectException(NoSuchElementException::class, $getFirstOfEmpty);

    $readMissing = function () use ($users): void {
        $users->column('email');
    };

    SmokeTest::expectException(InvalidPropertyOrMethod::class, $readMissing, "email");
});

$smoke->run("mutable: sort, filter and where return a new collection", function () use ($makeUsers, $getNames): void {
    $users = $makeUsers();
    SmokeTest::assertSame(['Ann', 'Bob', 'Cat'], $getNames($users->sort('name')), "sort() by a property");
    SmokeTest::assertSame(['Ann', 'Cat', 'Bob'], $getNames($users->sort('getAge', Sort::DESCENDING)), "descending");

    $isOver30 = function (User $user): bool {
        return $user->getAge() > 30;
    };

    $over30 = $users->filter($isOver30);
    SmokeTest::assertSame(MutableUserCollection::class, get_class($over30), "the class filter() returns");
    SmokeTest::assertSame(['Ann', 'Cat'], $getNames($over30), "filter()");
    SmokeTest::assertSame([0, 1], array_keys($over30->toArray()), "filter() numbers from 0");
    SmokeTest::assertSame(['Cat'], $getNames($users->where('name', 'Cat')), "where()");
    SmokeTest::assertSame(['Bob', 'Ann', 'Cat'], $getNames($users), "the original is unchanged");
    $over30->add(new User('Dan', 50));
    SmokeTest::assertSame(3, count($users), "changing the new collection doesn't change the original");
});

$smoke->run("mutable: filter removes every rejected element, even several in a row", function (): void {
    $ages = new MutableIntCollection(2, 4, 6, 1, 3, 8, 8, 5);

    $isOdd = function (int $age): bool {
        return $age % 2 === 1;
    };

    SmokeTest::assertSame([1, 3, 5], $ages->filter($isOdd)->toArray(), "kept");
});

$smoke->run("mutable: map and reduce", function () use ($makeUsers): void {
    $users = $makeUsers();

    $getInitial = function (User $user): string {
        return $user->name[0];
    };

    SmokeTest::assertSame(['B', 'A', 'C'], $users->map($getInitial), "map()");

    $addAge = function (int $total, User $user): int {
        return $total + $user->getAge();
    };

    SmokeTest::assertSame(98, $users->reduce($addAge, 0), "reduce()");
});

$smoke->run("mutable: merge, diff and intersect", function () use ($makeUsers, $ann, $getNames): void {
    $users = $makeUsers();
    $dan = new User('Dan', 50);
    $merged = $users->merge(new MutableUserCollection($dan));
    SmokeTest::assertSame(['Bob', 'Ann', 'Cat', 'Dan'], $getNames($merged), "merge()");
    SmokeTest::assertSame(3, count($users), "the original is unchanged");
    $keyed = (new MutableUserCollection(...['a' => $ann]))->merge(new MutableUserCollection(...['a' => $dan]));
    SmokeTest::assertSame(['a' => $dan], $keyed->toArray(), "merge() replaces a string key");
    $others = new MutableUserCollection($ann, $dan);
    SmokeTest::assertSame(['Bob', 'Cat', 'Dan'], $getNames($users->diff($others)), "diff()");
    SmokeTest::assertSame(['Ann'], $getNames($users->intersect($others)), "intersect()");

    $mergeOtherClass = function () use ($users, $dan): void {
        $users->merge(new LabelledUserCollection('Staff', $dan));
    };

    SmokeTest::expectException(CollectionMismatchException::class, $mergeOtherClass);
});

$smoke->run("mutable: the methods that return a new collection keep its other values", function () use ($ann): void {
    $staff = new LabelledUserCollection('Staff', $ann, new User('Bob', 25));

    $isAnn = function (User $user): bool {
        return $user->name === 'Ann';
    };

    SmokeTest::assertSame('Staff', $staff->filter($isAnn)->getLabel(), "filter() kept the label");
    SmokeTest::assertSame('Staff', $staff->sort('name')->getLabel(), "sort() kept the label");
});

$smoke->run("mutable: looping, and changing the collection during a loop", function () use ($makeUsers): void {
    $users = $makeUsers();
    $names = [];

    foreach ($users as $user)
    {
        $names[] = $user->name;
        $users->add(new User('Added', 1));
    }

    SmokeTest::assertSame(['Bob', 'Ann', 'Cat'], $names, "the loop went over the elements it started with");
    SmokeTest::assertSame(6, count($users), "the elements added during the loop are there");
});

$smoke->run("mutable: scalar and array elements", function (): void {
    $ages = new MutableIntCollection(3, 1, 2);
    SmokeTest::assertSame([1, 2, 3], $ages->sort()->toArray(), "sort() of ints");

    $readProperty = function () use ($ages): void {
        $ages->column('value');
    };

    SmokeTest::expectException(UnsupportedOperationException::class, $readProperty);
    $rows = new MutableArrayCollection(['id' => 2, 'name' => 'b'], ['id' => 1, 'name' => 'a']);
    SmokeTest::assertSame([2, 1], $rows->column('id'), "column() of arrays");
    SmokeTest::assertSame(['a', 'b'], $rows->sort('id')->column('name'), "sort() of arrays by a key");
});

$smoke->run("mutable: unserialize checks the elements' type", function () use ($makeUsers): void {
    $users = $makeUsers();
    $copy = unserialize(serialize($users));
    assert($copy instanceof MutableUserCollection, "it didn't unserialize as a MutableUserCollection");
    SmokeTest::assertSame(['Bob', 'Ann', 'Cat'], $copy->column('name'), "round trip");
    $tampered = 'O:' . strlen(MutableIntCollection::class) . ':"' . MutableIntCollection::class . '":1:{i:0;s:1:"x";}';

    $unserializeTampered = function () use ($tampered): void {
        unserialize($tampered);
    };

    SmokeTest::expectException(InvalidArgumentException::class, $unserializeTampered, "was given string");
});

$smoke->run("mutable: every exception can be caught as a CollectionException", function (): void {
    $getLastOfEmpty = function (): void {
        (new MutableUserCollection())->last();
    };

    SmokeTest::expectException(CollectionException::class, $getLastOfEmpty);
});
