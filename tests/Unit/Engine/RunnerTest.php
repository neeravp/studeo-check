<?php

use Studeo\Check\Engine\Baseline;
use Studeo\Check\Engine\PathSourceSet;
use Studeo\Check\Engine\Rule;
use Studeo\Check\Engine\RuleSet;
use Studeo\Check\Engine\Runner;
use Studeo\Check\Engine\Scope;
use Studeo\Check\Engine\Severity;
use Studeo\Check\Engine\SourceSet;
use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\FileLength;

it('runs a rule set over sources and orders violations by file, line, rule, subject', function () {
    $dir = sys_get_temp_dir().'/runner-test-'.bin2hex(random_bytes(6));
    mkdir("{$dir}/b", 0777, true);
    mkdir("{$dir}/a", 0777, true);
    file_put_contents("{$dir}/b/z.php", "<?php\n".str_repeat("\$a = 1;\n", 240));
    file_put_contents("{$dir}/a/m.php", "<?php\n".str_repeat("\$a = 1;\n", 240));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($dir)));

    $sources = new PathSourceSet($dir, [$dir]);
    $rules = RuleSet::of(FileLength::options(target: 10, hard: 20, scope: Scope::ALL));

    $result = (new Runner($rules))->run($sources);

    expect($result->violations)->toHaveCount(2)
        ->and($result->violations[0]->file)->toBe('a/m.php')
        ->and($result->violations[1]->file)->toBe('b/z.php')
        ->and($result->errorCount())->toBe(2);
});

it('yields a syntax violation for a file that does not parse, never an exception', function () {
    $dir = sys_get_temp_dir().'/runner-syntax-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    file_put_contents("{$dir}/broken.php", "<?php\n\n\$x = ;\n");
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($dir)));

    $sources = new PathSourceSet($dir, [$dir]);
    $rules = RuleSet::of(FileLength::options());

    $result = (new Runner($rules))->run($sources);

    expect($result->violations)->toHaveCount(1)
        ->and($result->violations[0]->rule)->toBe('syntax')
        ->and($result->violations[0]->file)->toBe('broken.php')
        ->and($result->violations[0]->line)->toBe(3)
        ->and($result->violations[0]->severity)->toBe(Severity::Error)
        ->and($result->violations[0]->subject)->toBe('file')
        ->and($result->violations[0]->message)->toBe("does not parse: Syntax error, unexpected ';'. Fix the syntax; no other rule can check this file until it parses.")
        ->and($result->errorCount())->toBe(1);
});

it('applies baseline suppressing matched violations and reporting stale entries', function () {
    $dir = sys_get_temp_dir().'/runner-base-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    file_put_contents("{$dir}/a.php", "<?php\n".str_repeat("\$a = 1;\n", 25));
    file_put_contents("{$dir}/b.php", "<?php\n".str_repeat("\$a = 1;\n", 25));
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($dir)));

    $sources = new PathSourceSet($dir, [$dir]);
    $rules = RuleSet::of(FileLength::options(target: 10, hard: 20, scope: Scope::ALL));
    $baseline = new Baseline([
        'file-length a.php file',
        'file-length c.php file',
    ]);

    $result = (new Runner($rules, $baseline))->run($sources);

    expect($result->violations)->toHaveCount(1)
        ->and($result->violations[0]->file)->toBe('b.php')
        ->and($result->staleBaseline)->toBe(['file-length c.php file'])
        ->and($result->errorCount())->toBe(2);
});

it('propagates InvalidRuleConfigurationException when thrown by a rule', function () {
    $dir = sys_get_temp_dir().'/runner-ex-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    file_put_contents("{$dir}/a.php", "<?php\n");
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($dir)));

    $sources = new PathSourceSet($dir, [$dir]);
    $explodingRule = new class implements Rule
    {
        public function name(): string
        {
            return 'exploding';
        }

        public function violations(SourceSet $sources): array
        {
            throw InvalidRuleConfigurationException::option('exploding', 'target', 'is invalid');
        }
    };
    $rules = RuleSet::of($explodingRule);

    expect(fn () => (new Runner($rules))->run($sources))
        ->toThrow(InvalidRuleConfigurationException::class, 'Rule [exploding] option [target] is invalid');
});
