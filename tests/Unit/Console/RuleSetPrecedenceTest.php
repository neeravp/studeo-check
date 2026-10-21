<?php

use Studeo\Check\Console\CheckCommand;
use Studeo\Check\Engine\RuleSet;
use Studeo\Check\Rules\StepCount;
use Symfony\Component\Console\Tester\CommandTester;

final class PrecedenceEmptyRuleSet
{
    public function rules(): RuleSet
    {
        return RuleSet::of();
    }
}

final class PrecedenceFindingRuleSet
{
    public function rules(): RuleSet
    {
        return RuleSet::of(StepCount::options(max: 1));
    }
}

it('prioritises --rules over a root check.php (case a)', function () {
    $busy = "<?php\n\nnamespace App\\Services;\n\nfinal class Busy\n{\n    public function go()\n    {\n".str_repeat("        \$a = 1;\n", 10)."    }\n}\n";
    $dir = createTestSandbox([
        'app/Services/Busy.php' => $busy,
        'check.php' => "<?php\nreturn \\PrecedenceEmptyRuleSet::class;\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['--rules' => PrecedenceFindingRuleSet::class]);

        expect($exit)->toBe(1)
            ->and($tester->getDisplay())->toContain('[step-count]')
            ->and($tester->getDisplay())->toContain('1 error, 0 warnings.');
    } finally {
        chdir($cwd);
    }
});

it('prioritises root check.php over Cap1Rules when no --rules is given (case b)', function () {
    $busy = "<?php\n\nnamespace App\\Services;\n\nfinal class Busy\n{\n    public function go()\n    {\n".str_repeat("        \$a = 1;\n", 10)."    }\n}\n";
    $dir = createTestSandbox([
        'app/Services/Busy.php' => $busy,
        'check.php' => "<?php\nreturn \\PrecedenceEmptyRuleSet::class;\n",
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

it('runs Cap1Rules when neither --rules nor check.php is given (case c)', function () {
    $busy = "<?php\n\nnamespace App\\Services;\n\nfinal class Busy\n{\n    public function go()\n    {\n".str_repeat("        \$a = 1;\n", 10)."    }\n}\n";
    $dir = createTestSandbox([
        'app/Services/Busy.php' => $busy,
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute([]);

        expect($exit)->toBe(1)
            ->and($tester->getDisplay())->toContain('[step-count]')
            ->and($tester->getDisplay())->toContain('1 error, 0 warnings.');
    } finally {
        chdir($cwd);
    }
});
