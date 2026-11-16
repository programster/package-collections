<?php

/*
 * Examples for the MapKeysRule test (see testing/tests/PhpStanRuleTest.php), which runs PHPStan over this file with
 * just the package's rule. Every line the rule should report ends in "// reported", and no other line may be. It's
 * never run, and it's left out of the package's own PHPStan run (see phpstan.neon.dist), as its mistakes are on
 * purpose.
 */

use Programster\Collections\Testing\Fixtures\MutableUserMap;
use Programster\Collections\Testing\Fixtures\User;
use Programster\Collections\Testing\Fixtures\UserCollection;
use Programster\Collections\Testing\Fixtures\UserMap;

$ann = new User('Ann', 40);
$bob = new User('Bob', 25);

/** @var array<string, User> $byName */
$byName = ['ann' => $ann];

/** @var list<User> $list */
$list = [$ann, $bob];

/** @var array<array-key, User> $anyKeys */
$anyKeys = ['ann' => $ann];

// The ways the documentation says to make a map, which mustn't be reported.
$spread = new UserMap(...['ann' => $ann, 'bob' => $bob]);
$named = new UserMap(ann: $ann, bob: $bob);
$fromTyped = new UserMap(...$byName);
$empty = new UserMap();
$mutable = new MutableUserMap(ann: $ann);

// A collection isn't a map, so its elements are passed by position.
$collection = new UserCollection($ann, $bob);

// The mistakes: elements without keys, or with keys that may not be strings.
$byPosition = new UserMap($ann); // reported
$mutableByPosition = new MutableUserMap($ann, $bob); // reported, reported
$numberKey = new UserMap(...['123' => $ann]); // reported
$fromList = new UserMap(...$list); // reported
$fromAnyKeys = new UserMap(...$anyKeys); // reported
