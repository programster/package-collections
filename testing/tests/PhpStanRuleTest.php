<?php

/*
 * Smoke test for the package's PHPStan rule (MapKeysRule). It runs PHPStan over testing/PhpStanRuleFixtures, and checks
 * the rule reports exactly the lines that are marked to be, so it catches each mistake and nothing that's correct.
 * These are run by testing/main.php, which provides $smoke.
 */

use Programster\Collections\Testing\SmokeTest;

/** @var \Programster\Collections\Testing\SmokeTest $smoke */

$testMapKeysRule = function (): void {
    $fixtures = __DIR__ . '/../PhpStanRuleFixtures';
    $examples = "{$fixtures}/MapKeysExamples.php";
    $lines = file($examples);
    $expected = [];

    if ($lines === false)
    {
        throw new RuntimeException("Couldn't read {$examples}.");
    }

    // Each "reported" in the comment after a statement is one error the rule should report on its line.
    foreach ($lines as $index => $line)
    {
        if (preg_match('/;\s*\/\/ (reported(?:, reported)*)\s*$/', $line, $matches) === 1)
        {
            $expected[$index + 1] = substr_count($matches[1], 'reported');
        }
    }

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../../vendor/phpstan/phpstan/phpstan')
        . ' analyse --no-progress --error-format=json --memory-limit=512M -c '
        . escapeshellarg("{$fixtures}/phpstan.neon") . ' 2>/dev/null';

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
            $isRule = is_array($message) && ($message['identifier'] ?? null) === 'collections.mapKeys';
            $line = is_array($message) && is_int($message['line'] ?? null) ? $message['line'] : 0;

            if ($isRule)
            {
                $reported[$line] = ($reported[$line] ?? 0) + 1;
            }
            else
            {
                throw new RuntimeException("PHPStan reported something other than the rule: " . json_encode($message));
            }
        }
    }

    ksort($reported);
    SmokeTest::assertSame($expected, $reported, "the lines the rule reports, and how many times");
};

$smoke->run("PHPStan rule: reports maps whose elements won't have string keys, and nothing else", $testMapKeysRule);
