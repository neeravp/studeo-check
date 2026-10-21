<?php

use Studeo\Check\Console\CheckCommand;
use Symfony\Component\Console\Tester\CommandTester;

it('exits 0 and prints warnings on a warning-only run without baseline', function () {
    $long = "<?php\n\nnamespace App\\Services;\n\n".str_repeat("\$x = 1;\n", 240);
    $dir = createTestSandbox([
        'app/Services/Long.php' => $long,
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['paths' => ['app']]);

        expect($exit)->toBe(0)
            ->and($tester->getDisplay())->toContain('app/Services/Long.php:1 [file-length]')
            ->and($tester->getDisplay())->toContain('exceeds the 230-line target')
            ->and($tester->getDisplay())->toContain('0 errors, 1 warning.');
    } finally {
        chdir($cwd);
    }
});

it('exits 1 on a warning when baseline lacks it', function () {
    $long = "<?php\n\nnamespace App\\Services;\n\n".str_repeat("\$x = 1;\n", 240);
    $dir = createTestSandbox([
        'app/Services/Long.php' => $long,
        'baseline.txt' => '',
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['paths' => ['app'], '--baseline' => 'baseline.txt']);

        expect($exit)->toBe(1)
            ->and($tester->getDisplay())->toContain('app/Services/Long.php:1 [file-length]');
    } finally {
        chdir($cwd);
    }
});

it('exits 0 on a warning when baseline lists it', function () {
    $long = "<?php\n\nnamespace App\\Services;\n\n".str_repeat("\$x = 1;\n", 240);
    $dir = createTestSandbox([
        'app/Services/Long.php' => $long,
        'baseline.txt' => "file-length app/Services/Long.php file\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['paths' => ['app'], '--baseline' => 'baseline.txt']);

        expect($exit)->toBe(0)
            ->and($tester->getDisplay())->toContain('0 errors, 0 warnings.');
    } finally {
        chdir($cwd);
    }
});

it('exits 1 on a stale warning entry in baseline', function () {
    $short = "<?php\n\nnamespace App\\Services;\n\n\$x = 1;\n";
    $dir = createTestSandbox([
        'app/Services/Short.php' => $short,
        'baseline.txt' => "file-length app/Services/Short.php file\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['paths' => ['app'], '--baseline' => 'baseline.txt']);

        expect($exit)->toBe(1)
            ->and($tester->getDisplay())->toContain('app/Services/Short.php:1 [baseline] Stale baseline entry for [file-length]');
    } finally {
        chdir($cwd);
    }
});
