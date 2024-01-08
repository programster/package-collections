# PHP Collections Package
A package to simplify the creation of strict type arrays in PHP.


## Example Usage
The example below creates a collection of users and sorts them by their name.


```php
<?php

require_once(__DIR__ . '/vendor/autoload.php');

class User
{
    public function __construct(public readonly string $name){}
}

class UserCollection extends \Programster\Collections\AbstractCollection
{
    public function __construct(User ...$elements)
    {
        parent::__construct(User::class, ...$elements);
    }
}

$names = new UserCollection(
    new User("Programster"),
    new User("Bar"),
    new User("Foo"),
);

// Demonstrate that can append a user, like with a normal array.
$names[] = new User("Added User");

$sortByNameFunc = function(User $name1, User $name2){
    return $name1->name <=> $name2->name;
};

$names->uasort($sortByNameFunc);
print "Users sorted by name: " . print_r($names, true) . PHP_EOL;
```






