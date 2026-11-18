<?php

/*
 * Smoke tests for AbstractEntry, the key and value that every map holds. These are run by testing/main.php, which
 * provides $smoke.
 */

use Programster\Collections\Testing\Fixtures\EntryWithExtraParameter;
use Programster\Collections\Testing\Fixtures\EntryWithFloatKey;
use Programster\Collections\Testing\Fixtures\EntryWithNullableValue;
use Programster\Collections\Testing\Fixtures\EntryWithUntypedValue;
use Programster\Collections\Testing\Fixtures\IntKeyEntry;
use Programster\Collections\Testing\Fixtures\StringEntry;
use Programster\Collections\Testing\Fixtures\User;
use Programster\Collections\Testing\Fixtures\UserEntry;
use Programster\Collections\Testing\SmokeTest;

/** @var \Programster\Collections\Testing\SmokeTest $smoke */

$ann = new User('Ann', 40);

$testCreating = function () use ($ann): void {
    $byName = new UserEntry('ann', $ann);
    $byId = new UserEntry(12, $ann);
    $byNumericString = new UserEntry('123', $ann);

    SmokeTest::assertSame('ann', $byName->getKey(), "a string key");
    SmokeTest::assertSame(12, $byId->getKey(), "an int key");
    SmokeTest::assertSame('123', $byNumericString->getKey(), "a string that's a number stays a string");
    SmokeTest::assertSame($ann, $byName->getValue(), "getValue()");
    SmokeTest::assertSame(User::class, UserEntry::getValueType(), "getValueType() is read from the constructor");
    SmokeTest::assertSame('string', StringEntry::getValueType(), "a built-in value type");
    SmokeTest::assertSame(7, (new IntKeyEntry(7, $ann))->getKey(), "a key declared as just int");
};

$smoke->run("entry: created with a key and a value", $testCreating);

$testRejectsWrongValues = function () use ($ann): void {
    $createWithWrongValue = function (): void {
        new UserEntry('ann', 'Ann'); // @phpstan-ignore argument.type (the wrong type, on purpose)
    };

    $createWithWrongKey = function () use ($ann): void {
        new UserEntry([], $ann); // @phpstan-ignore argument.type (the wrong type, on purpose)
    };

    SmokeTest::expectException(\TypeError::class, $createWithWrongValue, "User");
    SmokeTest::expectException(\TypeError::class, $createWithWrongKey, 'Argument #1 ($key) must be of type');
};

$smoke->run("entry: PHP refuses a value or key of the wrong type, where the entry is created", $testRejectsWrongValues);

$testRejectsBadEntryClasses = function () use ($ann): void {
    $createUntyped = function () use ($ann): void {
        new EntryWithUntypedValue('ann', $ann);
    };

    $createNullable = function () use ($ann): void {
        new EntryWithNullableValue('ann', $ann);
    };

    $createWithFloatKey = function () use ($ann): void {
        new EntryWithFloatKey(1.0, $ann);
    };

    $createWithExtraParameter = function () use ($ann): void {
        new EntryWithExtraParameter('ann', $ann, 'label');
    };

    SmokeTest::expectException(\LogicException::class, $createUntyped, "the one type its value is");
    SmokeTest::expectException(\LogicException::class, $createNullable, "can't be nullable");
    SmokeTest::expectException(\LogicException::class, $createWithFloatKey, "its key as int|string");
    SmokeTest::expectException(\LogicException::class, $createWithExtraParameter, "just the key and then the value");
};

$smoke->run("entry: a class without just a key and one typed value is refused", $testRejectsBadEntryClasses);
