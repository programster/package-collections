<?php

/*
 * Examples for the ReadonlyWriteRule test (see testing/tests/PhpStanRuleTest.php), which runs PHPStan over this file
 * with just the package's rules. Every line the rule should report ends in "// reported", and no other line may be.
 * It's never run, and it's left out of the package's own PHPStan run (see phpstan.neon.dist), as its mistakes are on
 * purpose.
 */

use Programster\Collections\Testing\Fixtures\MutableUserCollection;
use Programster\Collections\Testing\Fixtures\MutableUserMap;
use Programster\Collections\Testing\Fixtures\ReadonlyStringCollection;
use Programster\Collections\Testing\Fixtures\User;
use Programster\Collections\Testing\Fixtures\UserCollection;
use Programster\Collections\Testing\Fixtures\UserEntry;
use Programster\Collections\Testing\Fixtures\UserMap;

$ann = new User('Ann', 40);
$users = new UserCollection($ann);
$userMap = new UserMap(new UserEntry('ann', $ann));
$names = new ReadonlyStringCollection('Ann');
$mutableUsers = new MutableUserCollection($ann);
$mutableMap = new MutableUserMap(new UserEntry('ann', $ann));

/** @var UserCollection|null $maybeUsers */
$maybeUsers = $users;

// Reading with array syntax, and writing to the elements or to mutable collections and maps, mustn't be reported.
$first = $users[0];
$hasFirst = isset($users[0]);
$second = $users[1] ?? null;
$annAgain = $userMap['ann'];
$users[0]->name;
$copy = $users;
$mutableUsers[] = $ann;
$mutableUsers[0] = $ann;
unset($mutableUsers[0]);
$mutableMap['bob'] = $ann;
unset($mutableMap['bob']);
$array = [];
$array[0] = $ann;

// The mistakes: every way of writing to a readonly collection or map with array syntax.
$users[] = $ann; // reported
$users[0] = $ann; // reported
$userMap['bob'] = $ann; // reported
unset($users[0]); // reported
unset($userMap['ann'], $users[0]); // reported, reported
$names[0] .= 'x'; // reported
$names[0] ??= 'x'; // reported
$names[0]++; // reported
--$names[0]; // reported
$users[0] = &$ann; // reported
[$users[0], $users[1]] = [$ann, $ann]; // reported, reported
list(, $userMap['ann']) = [$ann, $ann]; // reported
$maybeUsers[0] = $ann; // reported

foreach ([$ann] as $users[0]) {} // reported
