<?php

use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\Laravel\Migration;
use Studeo\Check\Tests\Support\RuleFiles;

it('passes a plain migration', function () {
    expect(runRule(Migration::options(), RuleFiles::migration('Schema::create("posts", fn ($table) => $table->string("status", 30)->index());')))->toBe([]);
});

it('flags ->enum() with its line', function () {
    $found = runRule(Migration::options(), RuleFiles::migration('$table->enum("status", ["a"]);'));

    expect(summarise($found))->toBe([RuleFiles::MIGRATION_PATH.':7 enum()'])
        ->and($found[0]->rule)->toBe('migration')
        ->and($found[0]->message)->toContain('->enum()')->toContain('string');
});

it('flags a reference to a domain class however it is named', function (string $use, string $body) {
    $found = runRule(Migration::options(), RuleFiles::migration($body, $use));

    expect($found)->toHaveCount(1)
        ->and($found[0]->message)->toContain('domain class');
})->with([
    'model' => ['use Studeo\\Blog\\Models\\Post;', ''],
    'action' => ['use Studeo\\Blog\\Actions\\Run;', ''],
    'data' => ['use Studeo\\Blog\\Data\\PostData;', ''],
    'enum' => ['use Studeo\\Blog\\Enums\\Status;', ''],
    'fully qualified use in code' => ['', '$x = \\Studeo\\Blog\\Models\\Post::class;'],
    'enum default' => ['use Studeo\\Blog\\Enums\\Status;', '$y = Status::Draft;'],
]);

it('does not mistake a class that merely ends in one of the names', function () {
    expect(runRule(Migration::options(), RuleFiles::migration('', 'use Illuminate\\Support\\Facades\\Schema;')))->toBe([]);
});

it('only looks at database/migrations by default', function () {
    $files = ['database/seeders/Seed.php' => phpFile('class Seed { function a() { $t->enum("a", []); } }')];

    expect(runRule(Migration::options(), $files))->toBe([])
        ->and(runRule(Migration::options(scope: ['database/seeders']), $files))->not->toBe([]);
});

it('takes the forbidden fragments from options', function () {
    expect(runRule(Migration::options(forbidden: ['\\Services\\']), RuleFiles::migration('', 'use Studeo\\Blog\\Services\\Mailer;')))->toHaveCount(1)
        ->and(runRule(Migration::options(forbidden: ['\\Services\\']), RuleFiles::migration('', 'use Studeo\\Blog\\Models\\Post;')))->toBe([]);
});

it('rejects invalid options naming the rule and the option', function (array $options, string $option) {
    expect(fn () => Migration::options(...$options))
        ->toThrow(InvalidRuleConfigurationException::class, "[migration] option [{$option}]");
})->with([
    'empty forbidden list' => [['forbidden' => []], 'forbidden'],
    'blank forbidden fragment' => [['forbidden' => ['']], 'forbidden'],
    'empty scope' => [['scope' => []], 'scope'],
]);
