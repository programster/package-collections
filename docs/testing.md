# Testing

The package is tested with smoke tests (in `testing/`), rather than PHPUnit, so there's no test framework to install
or keep working on every version of PHP. They call every public method of the collections, and check that every way of
misusing one is rejected. A test fails if it throws, fails an `assert()`, or raises any warning, notice or deprecation.

## Running them on every supported version of PHP
This lints every file and runs the tests on PHP 8.2, 8.3, 8.4 and 8.5, each in its own Docker container, then runs
PHPStan once, on the lowest version. It's what the GitHub workflow runs, so a pass here means a pass there.

```bash
composer install
testing/run-all-php-versions.sh
```

- To test only some versions, pass them, e.g. `testing/run-all-php-versions.sh 8.2 8.5`.
- To rebuild the Docker images first, e.g. to pick up the latest patch releases, pass `--rebuild`.

## Running them on your own PHP
```bash
php -d zend.assertions=1 testing/main.php
```

`zend.assertions` must be 1, or the `assert()` checks would silently do nothing, so the tests refuse to run without it.
To run only the tests whose name contains some text, set `COLLECTIONS_TEST_ONLY`, e.g.
`COLLECTIONS_TEST_ONLY=readonly php -d zend.assertions=1 testing/main.php`.

## PHPStan
```bash
vendor/bin/phpstan
```

It runs at the highest level, 10, set in `phpstan.neon.dist`. The only errors it ignores are the ones the tests cause
on purpose, by passing the wrong type to check that it's rejected, and the base class's `new static()`, which is safe
because the constructor checks every collection's constructor takes just its elements.

## Adding a test
Each class has a file of tests in `testing/tests/`, e.g. `AbstractReadonlyCollectionTest.php`, and the classes the
tests use are in `testing/Fixtures/`. A test is a named callback:

```php
$smoke->run("readonly: first() of an empty collection throws", function (): void {
    $getFirstOfEmpty = function (): void {
        (new UserCollection())->first();
    };

    SmokeTest::expectException(NoSuchElementException::class, $getFirstOfEmpty);
});
```
