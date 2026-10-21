<?php

use Studeo\Check\Console\CheckCommand;
use Symfony\Component\Console\Tester\CommandTester;

it('exits 0 on a clean tree', function () {
    $dir = createTestSandbox([
        'app/Services/Clean.php' => "<?php\n\nnamespace App\\Services;\n\nfinal class Clean {}\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute([]);

        expect($exit)->toBe(0)
            ->and($tester->getDisplay())->toContain('0 errors, 0 warnings.');
    } finally {
        chdir($cwd);
    }
});

it('exits 1 on a seeded violation in text and in json', function () {
    $busy = "<?php\n\nnamespace App\\Services;\n\nfinal class Busy\n{\n    public function go()\n    {\n".str_repeat("        \$a = 1;\n", 10)."    }\n}\n";
    $dir = createTestSandbox([
        'app/Services/Busy.php' => $busy,
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);

        // Text format
        $exitText = $tester->execute(['paths' => ['app']]);
        expect($exitText)->toBe(1)
            ->and($tester->getDisplay())->toContain('app/Services/Busy.php:7 [step-count]')
            ->and($tester->getDisplay())->toContain('1 error, 0 warnings.');

        // JSON format
        $exitJson = $tester->execute(['paths' => ['app'], '--format' => 'json']);
        expect($exitJson)->toBe(1);
        $decoded = json_decode($tester->getDisplay(), true);
        expect($decoded)->toHaveCount(1)
            ->and($decoded[0]['rule'])->toBe('step-count')
            ->and($decoded[0]['file'])->toBe('app/Services/Busy.php')
            ->and($decoded[0]['subject'])->toBe('Busy::go')
            ->and($decoded[0]['severity'])->toBe('error');
    } finally {
        chdir($cwd);
    }
});

it('reports a syntax error finding and exits 1', function () {
    $dir = createTestSandbox([
        'app/Services/Broken.php' => "<?php\n\n\$x = ;\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['paths' => ['app']]);

        expect($exit)->toBe(1)
            ->and($tester->getDisplay())->toContain('app/Services/Broken.php:3 [syntax] does not parse');
    } finally {
        chdir($cwd);
    }
});

it('handles baseline: new violation fails, stale entry fails, update-baseline rewrites and exits 0', function () {
    $busy = "<?php\n\nnamespace App\\Services;\n\nfinal class Busy\n{\n    public function go()\n    {\n".str_repeat("        \$a = 1;\n", 10)."    }\n}\n";
    $dir = createTestSandbox([
        'app/Services/Busy.php' => $busy,
        'baseline.txt' => "step-count app/Services/Other.php Other::stale\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);

        // Run with baseline having stale entry and missing new violation -> exits 1
        $exit1 = $tester->execute(['paths' => ['app'], '--baseline' => 'baseline.txt']);
        expect($exit1)->toBe(1)
            ->and($tester->getDisplay())->toContain('app/Services/Busy.php:7 [step-count]')
            ->and($tester->getDisplay())->toContain('app/Services/Other.php:1 [baseline] Stale baseline entry');

        // Update baseline -> exits 0
        $exitUpdate = $tester->execute(['paths' => ['app'], '--baseline' => 'baseline.txt', '--update-baseline' => true]);
        expect($exitUpdate)->toBe(0);
        expect(file_get_contents("{$dir}/baseline.txt"))->toBe("step-count app/Services/Busy.php Busy::go\n");

        // Run again with updated baseline -> clean (0)
        $exitClean = $tester->execute(['paths' => ['app'], '--baseline' => 'baseline.txt']);
        expect($exitClean)->toBe(0)
            ->and($tester->getDisplay())->toContain('0 errors, 0 warnings.');
    } finally {
        chdir($cwd);
    }
});

it('discovers rule set class from check.php in base path', function () {
    $dir = createTestSandbox([
        'app/Services/Clean.php' => "<?php\n",
        'check.php' => "<?php\n\nreturn Studeo\\Check\\Profiles\\Cap1Rules::class;\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute([]);

        expect($exit)->toBe(0);
    } finally {
        chdir($cwd);
    }
});

it('produces the same finding for app, app/Services, and app/Services/Busy.php', function (string $path) {
    $busy = "<?php\n\nnamespace App\\Services;\n\nfinal class Busy\n{\n    public function go(): int\n    {\n        \$a = 1;\n        \$b = 2;\n        \$c = 3;\n        \$d = 4;\n        \$e = 5;\n        \$f = 6;\n        \$g = 7;\n        \$h = 8;\n        \$i = 9;\n        return \$a;\n    }\n}\n";
    $dir = createTestSandbox([
        'app/Services/Busy.php' => $busy,
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['paths' => [$path]]);

        expect($exit)->toBe(1)
            ->and($tester->getDisplay())->toContain('app/Services/Busy.php:7 [step-count]')
            ->and($tester->getDisplay())->toContain('1 error, 0 warnings.');
    } finally {
        chdir($cwd);
    }
})->with([
    'app',
    'app/Services',
    'app/Services/Busy.php',
]);

it('produces the same finding file path for dot, directory, and file arguments without leading dot-slash', function () {
    $busy = "<?php\n\nnamespace App\\Services;\n\nfinal class Busy\n{\n    public function go(): int\n    {\n        \$a = 1;\n        \$b = 2;\n        \$c = 3;\n        \$d = 4;\n        \$e = 5;\n        \$f = 6;\n        \$g = 7;\n        \$h = 8;\n        \$i = 9;\n        return \$a;\n    }\n}\n";
    $dir = createTestSandbox([
        'app/Services/Busy.php' => $busy,
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);

        $tester->execute(['paths' => ['.'], '--format' => 'json']);
        $dotResult = json_decode($tester->getDisplay(), true);

        $tester->execute(['paths' => ['app'], '--format' => 'json']);
        $appResult = json_decode($tester->getDisplay(), true);

        $tester->execute(['paths' => ['app/Services/Busy.php'], '--format' => 'json']);
        $fileResult = json_decode($tester->getDisplay(), true);

        expect($dotResult)->toHaveCount(1)
            ->and($dotResult[0]['file'])->toBe('app/Services/Busy.php')
            ->and($dotResult[0]['file'])->toBe($appResult[0]['file'])
            ->and($dotResult[0]['file'])->toBe($fileResult[0]['file'])
            ->and($dotResult[0]['file'])->not->toStartWith('./');

        $tester->execute(['paths' => ['.']]);
        $textDisplay = $tester->getDisplay();
        expect($textDisplay)->toContain('app/Services/Busy.php:7 [step-count]')
            ->and($textDisplay)->not->toContain('./app/Services/Busy.php');
    } finally {
        chdir($cwd);
    }
});
