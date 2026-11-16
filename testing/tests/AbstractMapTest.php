<?php

/*
 * Smoke tests for the mutable AbstractMap. These are run by testing/main.php, which provides $smoke.
 */

use Programster\Collections\Exceptions\InvalidArgumentException;
use Programster\Collections\Exceptions\NoSuchElementException;
use Programster\Collections\Testing\Fixtures\MutableUserMap;
use Programster\Collections\Testing\Fixtures\UntypedUserMap;
use Programster\Collections\Testing\Fixtures\User;
use Programster\Collections\Testing\SmokeTest;

/** @var \Programster\Collections\Testing\SmokeTest $smoke */

$ann = new User('Ann', 40);
$bob = new User('Bob', 25);

$testPutAndRemove = function () use ($ann, $bob): void {
    $users = new MutableUserMap(ann: $ann);
    $users->put('bob', $bob);
    SmokeTest::assertSame(['ann' => $ann, 'bob' => $bob], $users->toArray(), "put() adds");

    $users->put('ann', $bob);
    SmokeTest::assertSame(['ann' => $bob, 'bob' => $bob], $users->toArray(), "put() replaces, in place");

    $users->remove('ann');
    SmokeTest::assertSame(['bob' => $bob], $users->toArray(), "remove()");

    $users->clear();
    SmokeTest::assertSame(true, $users->isEmpty(), "clear()");

    $removeMissing = function () use ($users): void {
        $users->remove('cat');
    };

    SmokeTest::expectException(NoSuchElementException::class, $removeMissing, "isn't there");
};

$smoke->run("mutable map: put(), remove() and clear()", $testPutAndRemove);

$testArraySyntax = function () use ($ann, $bob): void {
    $users = new MutableUserMap(ann: $ann);
    $users['bob'] = $bob;

    SmokeTest::assertSame($bob, $users['bob'], "\$map['key'] = and reading it back");
    SmokeTest::assertSame(true, isset($users['ann']), "isset() for a key that's there");
    SmokeTest::assertSame(false, isset($users['cat']), "isset() for one that isn't");
    SmokeTest::assertSame(null, $users['cat'] ?? null, "?? for a key that isn't there");

    unset($users['ann']);
    SmokeTest::assertSame(['bob' => $bob], $users->toArray(), "unset()");

    $append = function () use ($users, $ann): void {
        $users[] = $ann;
    };

    $readMissing = function () use ($users): void {
        $users['cat'];
    };

    $unsetMissing = function () use ($users): void {
        unset($users['cat']);
    };

    SmokeTest::expectException(InvalidArgumentException::class, $append, "needs a string key");
    SmokeTest::expectException(NoSuchElementException::class, $readMissing, "\"cat\"");
    SmokeTest::expectException(NoSuchElementException::class, $unsetMissing, "isn't there");
};

$smoke->run("mutable map: array syntax, defensively", $testArraySyntax);

$testRejectsWrongType = function () use ($ann): void {
    $users = new MutableUserMap(ann: $ann);

    $putWrongType = function () use ($users): void {
        $users->put('number', 5);
    };

    $createUntyped = function (): void {
        new UntypedUserMap(number: 5);
    };

    $createByPosition = function () use ($ann): void {
        new MutableUserMap($ann);
    };

    SmokeTest::expectException(InvalidArgumentException::class, $putWrongType, "can only hold");
    SmokeTest::expectException(\LogicException::class, $createUntyped, "must declare the one type");
    SmokeTest::expectException(InvalidArgumentException::class, $createByPosition, "needs a key");
};

$smoke->run("mutable map: an element of the wrong type, or without a key, is refused", $testRejectsWrongType);

$testFilterKeepsOriginal = function () use ($ann, $bob): void {
    $users = new MutableUserMap(ann: $ann, bob: $bob);

    $isOver30 = function (User $user, string $key): bool {
        return $user->getAge() > 30;
    };

    SmokeTest::assertSame(['ann' => $ann], $users->filter($isOver30)->toArray(), "filter() returns a new map");
    SmokeTest::assertSame(2, count($users), "and leaves the original as it was");
    SmokeTest::assertSame('{}', json_encode(new MutableUserMap()), "an empty map encodes as an empty object");
};

$smoke->run("mutable map: filter() returns a new map, and JSON", $testFilterKeepsOriginal);
