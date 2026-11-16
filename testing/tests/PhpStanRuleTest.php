<?php

/*
 * Smoke test for the package's PHPStan rules (MapKeysRule and ReadonlyWriteRule). It runs PHPStan over
 * testing/PhpStanRuleFixtures, and checks each rule reports exactly the lines in its examples that are marked to be, so
 * it catches each mistake and nothing that's correct. These are run by testing/main.php, which provides $smoke.
 */

use Programster\Collections\Testing\SmokeTest;

/** @var \Programster\Collections\Testing\SmokeTest $smoke */

$fixtures = __DIR__ . '/../PhpStanRuleFixtures';

/*
 * Check a rule reports exactly the lines of its examples file that end in "// reported" (once for each "reported"),
 * and that nothing else in the file is reported, by running PHPStan over just that file, with just the package's rules.
 */
$testRule = function (string $examplesFile, string $identifier) use ($fixtures): void {
    $examples = "{$fixtures}/{$examplesFile}";
    $lines = file($examples);
    $expected = [];

    if ($lines === false)
    {
        throw new RuntimeException("Couldn't read {$examples}.");
    }

    // Each "reported" in the comment after a statement is one error the rule should report on its line.
    foreach ($lines as $index => $line)
    {
        if (preg_match('/[;}]\s*\/\/ (reported(?:, reported)*)\s*$/', $line, $matches) === 1)
        {
            $expected[$index + 1] = substr_count($matches[1], 'reported');
        }
    }

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../../vendor/phpstan/phpstan/phpstan')
        . ' analyse --no-progress --error-format=json --memory-limit=512M -c '
        . escapeshellarg("{$fixtures}/phpstan.neon") . ' ' . escapeshellarg($examples) . ' 2>/dev/null';

    $output = shell_exec($command);
    $result = is_string($output) ? json_decode($output, true) : null;

    if (!is_array($result) || !is_array($result['files'] ?? null))
    {
        throw new RuntimeException("PHPStan didn't give its results as JSON: " . var_export($output, true));
    }

    SmokeTest::assertSame([], $result['errors'] ?? [], "PHPStan ran without errors of its own");
    $reported = [];

    foreach ($result['files'] as $file)
    {
        $messages = is_array($file) && is_array($file['messages'] ?? null) ? $file['messages'] : [];

        foreach ($messages as $message)
        {
            $isRule = is_array($message) && ($message['identifier'] ?? null) === $identifier;
            $line = is_array($message) && is_int($message['line'] ?? null) ? $message['line'] : 0;

            if ($isRule)
            {
                $reported[$line] = ($reported[$line] ?? 0) + 1;
            }
            else
            {
                $json = json_encode($message);
                throw new RuntimeException("PHPStan reported something other than {$identifier}: {$json}");
            }
        }
    }

    ksort($reported);
    SmokeTest::assertSame($expected, $reported, "the lines {$identifier} reports, and how many times");
};

$testMapKeysRule = function () use ($testRule): void {
    $testRule('MapKeysExamples.php', 'collections.mapKeys');
};

$testReadonlyWriteRule = function () use ($testRule): void {
    $testRule('ReadonlyWriteExamples.php', 'collections.readonlyWrite');
};

$smoke->run("PHPStan rule: reports maps whose elements won't have string keys, and nothing else", $testMapKeysRule);

$smoke->run(
    "PHPStan rule: reports writes to readonly collections and maps with array syntax, and nothing else",
    $testReadonlyWriteRule
);
