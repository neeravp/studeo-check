<?php

use Symfony\Component\Process\Process;

function specCaseDirectories(): array
{
    $root = dirname(__DIR__, 3);
    $files = glob("{$root}/spec/*/*/expected.json");
    if ($files === false) {
        return [];
    }

    $cases = [];
    foreach ($files as $file) {
        $dir = dirname($file);
        $key = substr($dir, strlen("{$root}/spec/"));
        $cases[$key] = [$dir];
    }

    return $cases;
}

it('replays fixture case cleanly against the check executable', function (string $caseDirectory) {
    $root = dirname(__DIR__, 3);
    $process = new Process(['sh', 'spec/replay.sh', $caseDirectory], $root, ['PHP' => PHP_BINARY]);
    $process->run();

    $output = trim($process->getOutput());
    $lines = explode("\n", $output);
    $lastLine = end($lines);

    expect($process->getExitCode())->toBe(0, "Fixture failed:\n{$output}")
        ->and($lastLine)->toBe('1 cases, 0 failed', "Fixture output:\n{$output}");
})->with(specCaseDirectories());

it('verifies the total count of golden specification fixture cases is exactly 35', function () {
    expect(specCaseDirectories())->toHaveCount(35);
});
