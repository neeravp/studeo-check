<?php

use Studeo\Check\Console\CheckCommand;
use Symfony\Component\Console\Tester\CommandTester;

it('exits 2 when check.php returns an object', function () {
    $dir = createTestSandbox([
        'app/Services/Clean.php' => "<?php\n",
        'check.php' => "<?php\nreturn new stdClass;\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute([], ['capture_stderr_separately' => true]);

        expect($exit)->toBe(2);
        expect($tester->getErrorOutput())->not->toBeEmpty();
    } finally {
        chdir($cwd);
    }
});

it('exits 2 when check.php returns an empty string', function () {
    $dir = createTestSandbox([
        'app/Services/Clean.php' => "<?php\n",
        'check.php' => "<?php\nreturn '';\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute([], ['capture_stderr_separately' => true]);

        expect($exit)->toBe(2);
        expect($tester->getErrorOutput())->not->toBeEmpty();
    } finally {
        chdir($cwd);
    }
});

it('exits 2 when check.php returns an array', function () {
    $dir = createTestSandbox([
        'app/Services/Clean.php' => "<?php\n",
        'check.php' => "<?php\nreturn [];\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute([], ['capture_stderr_separately' => true]);

        expect($exit)->toBe(2);
        expect($tester->getErrorOutput())->not->toBeEmpty();
    } finally {
        chdir($cwd);
    }
});

it('exits 2 when check.php returns false', function () {
    $dir = createTestSandbox([
        'app/Services/Clean.php' => "<?php\n",
        'check.php' => "<?php\nreturn false;\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute([], ['capture_stderr_separately' => true]);

        expect($exit)->toBe(2);
        expect($tester->getErrorOutput())->not->toBeEmpty();
    } finally {
        chdir($cwd);
    }
});
