<?php

/*
 * Smoke tests for the mutable AbstractMap. These are run by testing/main.php, which provides $smoke.
 */

use Programster\Collections\Exceptions\InvalidArgumentException;
use Programster\Collections\Exceptions\NoSuchElementException;
use Programster\Collections\Testing\Fixtures\MutableUserMap;
use Programster\Collections\Testing\Fixtures\OtherUserEntry;
use Programster\Collections\Testing\Fixtures\UntypedUserMap;
use Programster\Collections\Testing\Fixtures\User;
use Programster\Collections\Testing\Fixtures\UserEntry;
use Programster\Collections\Testing\SmokeTest;

/** @var \Programster\Collections\Testing\SmokeTest $smoke */

$ann = new User('Ann', 40);
$bob = new User('Bob', 25);

$testPutAndRemove = function () use ($ann, $bob): void {
    $users = new MutableUserMap(new UserEntry('ann', $ann));
    $users->put(new UserEntry(12, $bob));
    SmokeTest::assertSame(['ann' => $ann, 12 => $bob], $users->toArray(), "put() adds");

    $users->put(new UserEntry('ann', $bob));
    SmokeTest::assertSame(['ann' => $bob, 12 => $bob], $users->toArray(), "put() replaces, in place");

    $users->remove(12);
    SmokeTest::assertSame(['ann' => $bob], $users->toArray(), "remove()");

    $users->clear();
    SmokeTest::assertSame(true, $users->isEmpty(), "clear()");

    $removeMissing = function () use ($users): void {
        $users->remove('cat');
    };

    SmokeTest::expectException(NoSuchElementException::class, $removeMissing, "isn't there");
};

$smoke->run("mutable map: put(), remove() and clear()", $testPutAndRemove);

$testArraySyntax = function () use ($ann, $bob): void {
    $users = new MutableUserMap(new UserEntry('ann', $ann));
    $users['bob'] = $bob;
    $users[] = new UserEntry(12, $ann);

    SmokeTest::assertSame($bob, $users['bob'], "\$map['key'] = \$value, and reading it back");
    SmokeTest::assertSame($ann, $users[12], "\$map[] = \$entry adds the entry under its own key");
    SmokeTest::assertSame(true, isset($users['ann']), "isset() for a key that's there");
    SmokeTest::assertSame(false, isset($users['cat']), "isset() for one that isn't");
    SmokeTest::assertSame(null, $users['cat'] ?? null, "?? for a key that isn't there");

    unset($users['ann']);
    SmokeTest::assertSame(['bob' => $bob, 12 => $ann], $users->toArray(), "unset()");

    $appendValue = function () use ($users, $ann): void {
        $users[] = $ann;
    };

    $putWrongType = function () use ($users): void {
        $users['number'] = 5;
    };

    $readMissing = function () use ($users): void {
        $users['cat'];
    };

    $unsetMissing = function () use ($users): void {
        unset($users['cat']);
    };

    SmokeTest::expectException(InvalidArgumentException::class, $appendValue, "adds a whole entry");
    SmokeTest::expectException(\TypeError::class, $putWrongType, "User");
    SmokeTest::expectException(NoSuchElementException::class, $readMissing, "\"cat\"");
    SmokeTest::expectException(NoSuchElementException::class, $unsetMissing, "isn't there");
};

$smoke->run("mutable map: array syntax, defensively", $testArraySyntax);

$testRejectsWrongEntries = function () use ($ann): void {
    $users = new MutableUserMap(new UserEntry('ann', $ann));

    $putOtherEntry = function () use ($users, $ann): void {
        $users->put(new OtherUserEntry('bob', $ann)); // @phpstan-ignore argument.type (another entry class)
    };

    $createUntyped = function (): void {
        new UntypedUserMap(5);
    };

    $createWithDuplicateKeys = function () use ($ann): void {
        new MutableUserMap(new UserEntry('ann', $ann), new UserEntry('ann', $ann));
    };

    SmokeTest::expectException(InvalidArgumentException::class, $putOtherEntry, "can only hold entries of");
    SmokeTest::expectException(\LogicException::class, $createUntyped, "must declare the one type");
    SmokeTest::expectException(InvalidArgumentException::class, $createWithDuplicateKeys, "use put()");
};

$smoke->run("mutable map: another entry class, or two entries with one key, is refused", $testRejectsWrongEntries);

$testFilterKeepsOriginal = function () use ($ann, $bob): void {
    $users = new MutableUserMap(new UserEntry('ann', $ann), new UserEntry('bob', $bob));

    $isOver30 = function (User $user, int|string $key): bool {
        return $user->getAge() > 30;
    };

    SmokeTest::assertSame(['ann' => $ann], $users->filter($isOver30)->toArray(), "filter() returns a new map");
    SmokeTest::assertSame(2, count($users), "and leaves the original as it was");
    SmokeTest::assertSame('{}', json_encode(new MutableUserMap()), "an empty map encodes as an empty object");
};

$smoke->run("mutable map: filter() returns a new map, and JSON", $testFilterKeepsOriginal);

$testIteratingWhileChanging = function () use ($ann, $bob): void {
    $users = new MutableUserMap(new UserEntry('ann', $ann), new UserEntry('bob', $bob));
    $seen = [];

    foreach ($users as $key => $user)
    {
        $seen[] = $key;
        $users->clear();
    }

    SmokeTest::assertSame(['ann', 'bob'], $seen, "foreach goes over a copy, so the map can change in the loop");
};

$smoke->run("mutable map: iterating over a copy", $testIteratingWhileChanging);
