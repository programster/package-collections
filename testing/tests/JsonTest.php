<?php

/*
 * Smoke tests that collections encode as JSON arrays of their elements. Without JsonSerializable, json_encode() would
 * silently give an empty object ({}) for a readonly collection, as its elements are in a private property.
 */

use Programster\Collections\Testing\Fixtures\MutableUserCollection;
use Programster\Collections\Testing\Fixtures\User;
use Programster\Collections\Testing\Fixtures\UserCollection;
use Programster\Collections\Testing\SmokeTest;

/** @var \Programster\Collections\Testing\SmokeTest $smoke */

$testReadonlyJson = function (): void {
    $users = new UserCollection(new User('Ann', 40), new User('Bob', 25));

    SmokeTest::assertSame('[{"name":"Ann"},{"name":"Bob"}]', json_encode($users), "a JSON array of the elements");
    SmokeTest::assertSame('[]', json_encode(new UserCollection()), "an empty collection is an empty array");
};

$smoke->run("readonly collection: encodes as a JSON array", $testReadonlyJson);

$testMutableJson = function (): void {
    $users = new MutableUserCollection(new User('Ann', 40));

    SmokeTest::assertSame('[{"name":"Ann"}]', json_encode($users), "a JSON array of the elements");
};

$smoke->run("mutable collection: encodes as a JSON array", $testMutableJson);
