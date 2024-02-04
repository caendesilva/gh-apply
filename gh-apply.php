#!/usr/bin/env php
<?php

// Simple cross platform PHP script to apply a Git patch from GitHub

// Supply the URL to the patch file as the first argument. It should follow one of the following formats:
// - https://github.com/user/repo/commit/2aae6c35c94fcfb415dbe95f408b9ce91ee846ed
// - https://github.com/user/repo/commit/2aae6c35c94fcfb415dbe95f408b9ce91ee846ed.patch
// - user/repo/2aae6c35c94fcfb415dbe95f408b9ce91ee846ed

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo 'This script is meant to be run from the command line';
    exit(1);
}

function getInput(): string
{
    global $argv;

    if (! isset($argv[1])) {
        echo 'No input provided';
        exit(1);
    }

    // Normalize the input
    $input = $argv[1];

    $input = trim($input);
    $input = trim($input, '/');

    // Rewrite http to https
    $input = str_replace('http://', 'https://', $input);

    // If not ends with .patch, append it
    if (! str_ends_with($input, '.patch')) {
        $input .= '.patch';
    }

    // If string just starts with github.com, prepend https://
    if (str_starts_with($input, 'github.com')) {
        $input = 'https://'.$input;
    }

    // If not starts https://github.com, prepend it
    if (! str_starts_with($input, 'https://github.com/')) {
        $input = 'https://github.com/'.$input;
    }

    // If it does not contain /commit/, inject that before the commit hash (which we find before the last /)
    if (! str_contains($input, '/commit/')) {
        $lastSlash = strrpos($input, '/');
        $input = substr($input, 0, $lastSlash).'/commit/'.substr($input, $lastSlash + 1);
    }

    // Validate the input format
    if (! preg_match('/^https:\/\/github.com\/[^\/]+\/[^\/]+\/commit\/[0-9a-f]{40}\.patch$/', $input)) {
        echo 'Invalid input format';
        exit(1);
    }

    return $input;
}

function shouldCommit(): bool
{
    global $argv;

    return isset($argv[2]) && ($argv[2] === '--commit' || $argv[2] === '-c');
}

function applyPatch(string $patch, bool $commit = false): int
{
    $patchFile = tempnam(sys_get_temp_dir(), 'patch');

    file_put_contents($patchFile, $patch);

    if ($commit) {
        // We need to use git am to apply the patch and commit it
        $command = 'git am --signoff < '.escapeshellarg($patchFile);
    } else {
        $command = 'git apply '.escapeshellarg($patchFile);
    }

    passthru($command, $exitCode);

    unlink($patchFile);

    return $exitCode;
}

function getPatch(string $input): string
{
    $patch = file_get_contents($input);

    if ($patch === false) {
        echo 'Failed to download the patch file';
        exit(1);
    }

    return $patch;
}

$input = getInput();
$commit = shouldCommit();

$patch = getPatch($input);

$exitCode = applyPatch($patch, $commit);

exit($exitCode);
