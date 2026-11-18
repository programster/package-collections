<?php

/*
 * Smoke tests for AbstractReadonlyMap. These are run by testing/main.php, which provides $smoke.
 */

use Programster\Collections\Exceptions\InvalidArgumentException;
use Programster\Collections\Exceptions\NoSuchElementException;
use Programster\Collections\Exceptions\UnsupportedOperationException;
use Programster\Collections\Testing\Fixtures\IntKeyEntry;
use Programster\Collections\Testing\Fixtures\IntKeyUserMap;
use Programster\Collections\Testing\Fixtures\MapOfAbstractEntries;
use Programster\Collections\Testing\Fixtures\MapOfUsers;
use Programster\Collections\Testing\Fixtures\MapWithExtraParameter;
use Programster\Collections\Testing\Fixtures\OtherUserEntry;
use Programster\Collections\Testing\Fixtures\StringEntry;
use Programster\Collections\Testing\Fixtures\StringMap;
use Programster\Collections\Testing\Fixtures\User;
use Programster\Collections\Testing\Fixtures\UserEntry;
use Programster\Collections\Testing\Fixtures\UserMap;
use Programster\Collections\Testing\SmokeTest;

/** @var \Programster\Collections\Testing\SmokeTest $smoke */

$ann = new User('Ann', 40);
$bob = new User('Bob', 25);

$testCreating = function () use ($ann, $bob): void {
    $users = new UserMap(new UserEntry('ann', $ann), new UserEntry(12, $bob));

    SmokeTest::assertSame(['ann' => $ann, 12 => $bob], $users->toArray(), "string and int keys, in order");
    SmokeTest::assertSame(['ann', 12], $users->keys(), "keys() gives each key as it was created");
    SmokeTest::assertSame(UserEntry::class, $users->getType(), "getType() is the map's entry class");
    SmokeTest::assertSame(true, (new UserMap())->isEmpty(), "an empty map");
    SmokeTest::assertSame(['x' => 'y'], (new StringMap(new StringEntry('x', 'y')))->toArray(), "a built-in type");
};

$smoke->run("readonly map: created from entries", $testCreating);

$testKeysThatLookLikeAList = function () use ($ann, $bob): void {
    $users = new UserMap(new UserEntry(0, $ann), new UserEntry(1, $bob));
    $byId = new IntKeyUserMap(new IntKeyEntry(0, $ann));

    SmokeTest::assertSame([0, 1], $users->keys(), "keys of 0 and 1 are kept, as each entry has its key");
    SmokeTest::assertSame($bob, $users->get(1), "and can be read");
    SmokeTest::assertSame('{"0":{"name":"Ann"},"1":{"name":"Bob"}}', json_encode($users), "JSON is still an object");
    SmokeTest::assertSame($ann, $byId->get(0), "an entry class that only allows int keys");
};

$smoke->run("readonly map: keys of 0, 1, 2 and so on are keys, not a list", $testKeysThatLookLikeAList);

$testIgnoresGivenKeys = function () use ($ann, $bob): void {
    $entries = ['first' => new UserEntry('ann', $ann), 'second' => new UserEntry('bob', $bob)];
    $users = new UserMap(...$entries);

    SmokeTest::assertSame(['ann', 'bob'], $users->keys(), "the keys of a spread array are ignored");
};

$smoke->run("readonly map: only each entry's own key is used", $testIgnoresGivenKeys);

$testNumericStringKeys = function () use ($ann, $bob): void {
    $users = new UserMap(new UserEntry('123', $ann));

    $createWithBoth = function () use ($ann, $bob): void {
        new UserMap(new UserEntry('123', $ann), new UserEntry(123, $bob));
    };

    SmokeTest::assertSame(['123'], $users->keys(), "a string key that's a number is given back as a string");
    SmokeTest::assertSame($ann, $users->get('123'), "get() with the string");
    SmokeTest::assertSame($ann, $users->get(123), "get() with the number, as PHP treats them as one key");
    SmokeTest::assertSame(['123' => $ann], iterator_to_array($users), "foreach gives the key as it was created");
    SmokeTest::expectException(InvalidArgumentException::class, $createWithBoth, "the same key as that number");
};

$smoke->run("readonly map: a string key that's a whole number", $testNumericStringKeys);

$testRejectsBadMaps = function () use ($ann): void {
    $createWithDuplicateKeys = function () use ($ann): void {
        new UserMap(new UserEntry('ann', $ann), new UserEntry('ann', new User('Ann', 41)));
    };

    $createWithExtraParameter = function () use ($ann): void {
        new MapWithExtraParameter('label', new UserEntry('ann', $ann));
    };

    $createOfAbstractEntries = function () use ($ann): void {
        new MapOfAbstractEntries(new UserEntry('ann', $ann));
    };

    $createOfUsers = function () use ($ann): void {
        new MapOfUsers($ann);
    };

    $createEmptyOfUsers = function (): void {
        new MapOfUsers();
    };

    SmokeTest::expectException(InvalidArgumentException::class, $createWithDuplicateKeys, "have the key \"ann\"");
    SmokeTest::expectException(\LogicException::class, $createWithExtraParameter, "just its elements");
    SmokeTest::expectException(\LogicException::class, $createOfAbstractEntries, "AbstractEntry itself");
    SmokeTest::expectException(\LogicException::class, $createOfUsers, "which isn't an entry");
    SmokeTest::expectException(\LogicException::class, $createEmptyOfUsers, "which isn't an entry");
};

$smoke->run(
    "readonly map: two entries with one key, or a map that doesn't take its own entries, is refused",
    $testRejectsBadMaps
);

$testReading = function () use ($ann, $bob): void {
    $users = new UserMap(new UserEntry('ann', $ann), new UserEntry('bob', $bob));

    $getMissing = function () use ($users): void {
        $users->get('cat');
    };

    SmokeTest::assertSame($bob, $users->get('bob'), "get() gives the value");
    SmokeTest::assertSame(true, $users->containsKey('ann'), "containsKey() for a key that's there");
    SmokeTest::assertSame(false, $users->containsKey('cat'), "containsKey() for one that isn't");
    SmokeTest::assertSame(true, $users->contains($ann), "contains() looks for a value");
    SmokeTest::assertSame(false, $users->contains(new User('Ann', 40)), "contains() compares with === by default");
    SmokeTest::assertSame(true, $users->contains(new User('Ann', 40), false), "contains() with == when asked");
    SmokeTest::assertSame([$ann, $bob], $users->values(), "values()");
    SmokeTest::assertSame(2, count($users), "count()");
    SmokeTest::expectException(NoSuchElementException::class, $getMissing, "\"cat\"");
};

$smoke->run("readonly map: reading values by key", $testReading);

$testEntries = function () use ($ann, $bob): void {
    $annEntry = new UserEntry('ann', $ann);
    $bobEntry = new UserEntry('bob', $bob);
    $users = new UserMap($annEntry, $bobEntry);
    $copy = new UserMap(...$users->getEntries());

    SmokeTest::assertSame([$annEntry, $bobEntry], $users->getEntries(), "getEntries() gives the entries, in order");
    SmokeTest::assertSame($users->toArray(), $copy->toArray(), "which can make another map");
};

$smoke->run("readonly map: getEntries()", $testEntries);

$testReadingWithArraySyntax = function () use ($ann, $bob): void {
    $users = new UserMap(new UserEntry('ann', $ann), new UserEntry(12, $bob));

    $getMissing = function () use ($users): void {
        $users['cat'];
    };

    $getByFloat = function () use ($users): void {
        $users[1.5];
    };

    SmokeTest::assertSame($bob, $users[12], "[12]");
    SmokeTest::assertSame($ann, $users['ann'], "['ann']");
    SmokeTest::assertSame(true, isset($users['ann']), "isset() of a key that's there");
    SmokeTest::assertSame(false, isset($users['cat']), "isset() of one that isn't");
    SmokeTest::assertSame(false, isset($users[1.5]), "isset() of a key that isn't an int or string");
    SmokeTest::assertSame(null, $users['cat'] ?? null, "?? of one that isn't");
    SmokeTest::expectException(NoSuchElementException::class, $getMissing, "\"cat\"");
    SmokeTest::expectException(NoSuchElementException::class, $getByFloat, "ints or strings, not float");
};

$smoke->run("readonly map: reading values with array syntax", $testReadingWithArraySyntax);

$testWritingWithArraySyntax = function () use ($ann, $bob): void {
    $users = new UserMap(new UserEntry('ann', $ann));

    $put = function () use ($users, $bob): void {
        $users['bob'] = $bob;
    };

    $append = function () use ($users, $bob): void {
        $users[] = new UserEntry('bob', $bob);
    };

    $unset = function () use ($users): void {
        unset($users['ann']);
    };

    SmokeTest::expectException(UnsupportedOperationException::class, $put, "Use with()");
    SmokeTest::expectException(UnsupportedOperationException::class, $append, "Use with()");
    SmokeTest::expectException(UnsupportedOperationException::class, $unset, "Use without()");
    SmokeTest::assertSame(['ann' => $ann], $users->toArray(), "the map's values after trying to write to it");
};

$smoke->run("readonly map: writing with array syntax throws", $testWritingWithArraySyntax);

$testIterating = function () use ($ann, $bob): void {
    $seen = [];

    foreach (new UserMap(new UserEntry('ann', $ann), new UserEntry(12, $bob)) as $key => $user)
    {
        $seen[] = [$key, $user->name];
    }

    SmokeTest::assertSame([['ann', 'Ann'], [12, 'Bob']], $seen, "foreach gives the keys and values, in order");
};

$smoke->run("readonly map: iterating gives the keys and values", $testIterating);

$testChanging = function () use ($ann, $bob): void {
    $users = new UserMap(new UserEntry('ann', $ann));
    $cat = new User('Cat', 33);
    $withBob = $users->with(new UserEntry('bob', $bob));
    $replaced = $withBob->with(new UserEntry('ann', $cat));

    $removeMissing = function () use ($users): void {
        $users->without('cat');
    };

    $addOtherEntry = function () use ($users, $bob): void {
        $users->with(new OtherUserEntry('bob', $bob)); // @phpstan-ignore argument.type (another entry class)
    };

    SmokeTest::assertSame(['ann' => $ann], $users->toArray(), "with() leaves the original as it was");
    SmokeTest::assertSame(['ann' => $ann, 'bob' => $bob], $withBob->toArray(), "with() adds");
    SmokeTest::assertSame(['ann' => $cat, 'bob' => $bob], $replaced->toArray(), "with() replaces, in place");
    SmokeTest::assertSame(['bob' => $bob], $withBob->without('ann')->toArray(), "without()");
    SmokeTest::expectException(NoSuchElementException::class, $removeMissing, "isn't there");
    SmokeTest::expectException(InvalidArgumentException::class, $addOtherEntry, "can only hold entries of");
};

$smoke->run("readonly map: with() and without() return new maps", $testChanging);

$testFilterAndMap = function () use ($ann, $bob): void {
    $users = new UserMap(new UserEntry('ann', $ann), new UserEntry(12, $bob));

    $isOver30 = function (User $user, int|string $key): bool {
        return $user->getAge() > 30;
    };

    $describe = function (User $user, int|string $key): string {
        return "{$key}: {$user->name}";
    };

    SmokeTest::assertSame(['ann' => $ann], $users->filter($isOver30)->toArray(), "filter() keeps the keys");
    SmokeTest::assertSame(['ann' => 'ann: Ann', 12 => '12: Bob'], $users->map($describe), "map() keeps the keys");
};

$smoke->run("readonly map: filter() and map() are given each value and key", $testFilterAndMap);

$testJson = function () use ($ann): void {
    SmokeTest::assertSame('{"ann":{"name":"Ann"}}', json_encode(new UserMap(new UserEntry('ann', $ann))), "an object");
    SmokeTest::assertSame('{}', json_encode(new UserMap()), "an empty map is an empty object, not []");
};

$smoke->run("readonly map: encodes as a JSON object of its values", $testJson);
