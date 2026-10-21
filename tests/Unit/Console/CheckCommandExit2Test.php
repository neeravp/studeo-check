<?php

use Studeo\Check\Console\CheckCommand;
use Symfony\Component\Console\Tester\CommandTester;

it('exits 2 on unreadable path', function () {
    $dir = createTestSandbox([
        'app/Services/Clean.php' => "<?php\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['paths' => ['missing/nonexistent']]);

        expect($exit)->toBe(2);
    } finally {
        chdir($cwd);
    }
});

it('exits 2 on missing --rules class', function () {
    $dir = createTestSandbox([
        'app/Services/Clean.php' => "<?php\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['--rules' => 'Missing\\RulesClass']);

        expect($exit)->toBe(2);
    } finally {
        chdir($cwd);
    }
});

it('exits 2 on class that is not a rule set', function () {
    $notARuleSet = "<?php\n\nnamespace App;\n\nfinal class NotARuleSet {}\n";
    $dir = createTestSandbox([
        'app/Services/Clean.php' => "<?php\n",
        'app/NotARuleSet.php' => $notARuleSet,
    ]);

    require_once "{$dir}/app/NotARuleSet.php";

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['--rules' => 'App\\NotARuleSet']);

        expect($exit)->toBe(2);
    } finally {
        chdir($cwd);
    }
});

it('exits 2 on rejected options', function () {
    $customRules = "<?php\n\nnamespace App;\n\nuse Studeo\Check\Engine\RuleSet;\nuse Studeo\Check\Rules\StepCount;\n\nfinal class BadRuleSet\n{\n    public function rules(): RuleSet\n    {\n        return RuleSet::of(StepCount::options(max: 0));\n    }\n}\n";
    $dir = createTestSandbox([
        'app/Services/Clean.php' => "<?php\n",
        'app/BadRuleSet.php' => $customRules,
    ]);

    require_once "{$dir}/app/BadRuleSet.php";

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['--rules' => 'App\\BadRuleSet']);

        expect($exit)->toBe(2);
    } finally {
        chdir($cwd);
    }
});

it('exits 2 on bad --format', function () {
    $dir = createTestSandbox([
        'app/Services/Clean.php' => "<?php\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['--format' => 'xml']);

        expect($exit)->toBe(2);
    } finally {
        chdir($cwd);
    }
});

it('exits 2 on --update-baseline without --baseline', function () {
    $dir = createTestSandbox([
        'app/Services/Clean.php' => "<?php\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['--update-baseline' => true]);

        expect($exit)->toBe(2);
    } finally {
        chdir($cwd);
    }
});

it('exits 2 on unreadable baseline file', function () {
    $dir = createTestSandbox([
        'app/Services/Clean.php' => "<?php\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['--baseline' => 'nonexistent-baseline.txt']);

        expect($exit)->toBe(2);
    } finally {
        chdir($cwd);
    }
});
