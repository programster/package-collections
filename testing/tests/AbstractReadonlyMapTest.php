<?php

/*
 * Smoke tests for AbstractReadonlyMap. These are run by testing/main.php, which provides $smoke.
 */

use Programster\Collections\Exceptions\InvalidArgumentException;
use Programster\Collections\Exceptions\NoSuchElementException;
use Programster\Collections\Testing\Fixtures\MapWithExtraParameter;
use Programster\Collections\Testing\Fixtures\User;
use Programster\Collections\Testing\Fixtures\UserMap;
use Programster\Collections\Testing\SmokeTest;

/** @var \Programster\Collections\Testing\SmokeTest $smoke */

$ann = new User('Ann', 40);
$bob = new User('Bob', 25);

$testCreating = function () use ($ann, $bob): void {
    $spread = new UserMap(...['ann' => $ann, 'bob' => $bob]);
    $named = new UserMap(ann: $ann, bob: $bob);

    SmokeTest::assertSame(['ann' => $ann, 'bob' => $bob], $spread->toArray(), "from a spread associative array");
    SmokeTest::assertSame(['ann' => $ann, 'bob' => $bob], $named->toArray(), "from named arguments");
    SmokeTest::assertSame(['the owner' => $ann], (new UserMap(...['the owner' => $ann]))->toArray(), "any string key");
    SmokeTest::assertSame(true, (new UserMap())->isEmpty(), "an empty map");
};

$smoke->run("readonly map: created from elements given by key", $testCreating);

$testRejectsMissingKeys = function () use ($ann): void {
    $createByPosition = function () use ($ann): void {
        new UserMap($ann);
    };

    $createWithNumericKey = function () use ($ann): void {
        new UserMap(...['123' => $ann]);
    };

    $createWithExtraParameter = function () use ($ann): void {
        new MapWithExtraParameter('label', ...['ann' => $ann]);
    };

    SmokeTest::expectException(InvalidArgumentException::class, $createByPosition, "needs a key");
    SmokeTest::expectException(InvalidArgumentException::class, $createWithNumericKey, "whole numbers");
    SmokeTest::expectException(\LogicException::class, $createWithExtraParameter, "just its elements");
};

$smoke->run("readonly map: an element without a key, or a bad constructor, is refused", $testRejectsMissingKeys);

$testReading = function () use ($ann, $bob): void {
    $users = new UserMap(ann: $ann, bob: $bob);

    $getMissing = function () use ($users): void {
        $users->get('cat');
    };

    SmokeTest::assertSame($bob, $users->get('bob'), "get()");
    SmokeTest::assertSame(true, $users->containsKey('ann'), "containsKey() for a key that's there");
    SmokeTest::assertSame(false, $users->containsKey('cat'), "containsKey() for one that isn't");
    SmokeTest::assertSame(true, $users->contains($ann), "contains()");
    SmokeTest::assertSame(false, $users->contains(new User('Ann', 40)), "contains() compares with === by default");
    SmokeTest::assertSame(true, $users->contains(new User('Ann', 40), false), "contains() with == when asked");
    SmokeTest::assertSame(['ann', 'bob'], $users->keys(), "keys()");
    SmokeTest::assertSame([$ann, $bob], $users->values(), "values()");
    SmokeTest::assertSame(2, count($users), "count()");
    SmokeTest::expectException(NoSuchElementException::class, $getMissing, "\"cat\"");
};

$smoke->run("readonly map: reading elements by key", $testReading);

$testIterating = function () use ($ann, $bob): void {
    $seen = [];

    foreach (new UserMap(ann: $ann, bob: $bob) as $key => $user)
    {
        $seen[$key] = $user->name;
    }

    SmokeTest::assertSame(['ann' => 'Ann', 'bob' => 'Bob'], $seen, "foreach gives the keys and elements, in order");
};

$smoke->run("readonly map: iterating gives the keys", $testIterating);

$testChanging = function () use ($ann, $bob): void {
    $users = new UserMap(ann: $ann);
    $cat = new User('Cat', 33);
    $withBob = $users->with('bob', $bob);
    $replaced = $withBob->with('ann', $cat);

    $removeMissing = function () use ($users): void {
        $users->without('cat');
    };

    $addWrongType = function () use ($users): void {
        $users->with('number', 5);
    };

    SmokeTest::assertSame(['ann' => $ann], $users->toArray(), "with() leaves the original as it was");
    SmokeTest::assertSame(['ann' => $ann, 'bob' => $bob], $withBob->toArray(), "with() adds");
    SmokeTest::assertSame(['ann' => $cat, 'bob' => $bob], $replaced->toArray(), "with() replaces, in place");
    SmokeTest::assertSame(['bob' => $bob], $withBob->without('ann')->toArray(), "without()");
    SmokeTest::expectException(NoSuchElementException::class, $removeMissing, "isn't there");
    SmokeTest::expectException(InvalidArgumentException::class, $addWrongType, "can only hold");
};

$smoke->run("readonly map: with() and without() return new maps", $testChanging);

$testFilterAndMap = function () use ($ann, $bob): void {
    $users = new UserMap(ann: $ann, bob: $bob);

    $isOver30 = function (User $user, string $key): bool {
        return $user->getAge() > 30;
    };

    $describe = function (User $user, string $key): string {
        return "{$key}: {$user->name}";
    };

    SmokeTest::assertSame(['ann' => $ann], $users->filter($isOver30)->toArray(), "filter() keeps the keys");
    SmokeTest::assertSame(['ann' => 'ann: Ann', 'bob' => 'bob: Bob'], $users->map($describe), "map() keeps the keys");
};

$smoke->run("readonly map: filter() and map() are given each key", $testFilterAndMap);

$testJson = function () use ($ann): void {
    SmokeTest::assertSame('{"ann":{"name":"Ann"}}', json_encode(new UserMap(ann: $ann)), "a JSON object");
    SmokeTest::assertSame('{}', json_encode(new UserMap()), "an empty map is an empty object, not []");
};

$smoke->run("readonly map: encodes as a JSON object", $testJson);
