<?php

/*
 * Smoke tests for the mutable AbstractCollection. These are run by testing/main.php, which provides $smoke.
 */

use Programster\Collections\Testing\Fixtures\MutableUserCollection;
use Programster\Collections\Testing\Fixtures\User;
use Programster\Collections\Testing\SmokeTest;

/** @var \Programster\Collections\Testing\SmokeTest $smoke */

$smoke->run("mutable: holds and appends elements of its type", function (): void {
    $users = new MutableUserCollection(new User('Ann', 40));
    $users[] = new User('Bob', 25);
    $users->append(new User('Cat', 33));
    $users->appendMultiple([new User('Dan', 50)]);
    SmokeTest::assertSame(4, count($users), "count");

    $appendString = function () use ($users): void {
        $users[] = 'not a user';
    };

    SmokeTest::expectException(\Exception::class, $appendString, "Cannot append");

    $createWithString = function (): void {
        new MutableUserCollection(...['not a user']);
    };

    SmokeTest::expectException(\TypeError::class, $createWithString);
});

$smoke->run("mutable: filter removes every rejected element, even several in a row", function (): void {
    $ages = [2, 4, 6, 1, 3, 8, 8, 5];

    $makeUser = function (int $age): User {
        return new User("Age {$age}", $age);
    };

    $users = new MutableUserCollection(...array_map($makeUser, $ages));

    $isOdd = function (User $user): bool {
        return $user->getAge() % 2 === 1;
    };

    $result = $users->filter($isOdd);
    assert($result === $users, "filter() didn't return the same collection");

    $getAge = function (User $user): int {
        return $user->getAge();
    };

    SmokeTest::assertSame([1, 3, 5], array_values(array_map($getAge, $users->getArrayCopy())), "kept");
});
