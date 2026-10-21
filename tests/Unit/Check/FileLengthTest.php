<?php

use Studeo\Check\Engine\Severity;
use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\FileLength;

function fileOfLines(int $lines): string
{
    return "<?php\n".str_repeat("\$a = 1;\n", $lines - 1);
}

it('reports a file over 300 lines as an error on line 1', function () {
    $found = runRule(FileLength::options(), ['Actions/Big.php' => fileOfLines(301)]);

    expect(summarise($found))->toBe(['modules/Blog/Actions/Big.php:1 file'])
        ->and($found[0]->rule)->toBe('file-length')
        ->and($found[0]->severity)->toBe(Severity::Error)
        ->and($found[0]->message)->toContain('301')->toContain('300')->toContain('Split');
});

it('reports a file over 230 lines as a warning', function () {
    $found = runRule(FileLength::options(), ['Actions/Mid.php' => fileOfLines(231)]);

    expect($found)->toHaveCount(1)
        ->and($found[0]->severity)->toBe(Severity::Warning)
        ->and($found[0]->message)->toContain('231')->toContain('230')->toContain('Extract');
});

it('passes files at the limits', function (int $lines) {
    expect(runRule(FileLength::options(), ['Actions/Ok.php' => fileOfLines($lines)]))->toBe([]);
})->with([230, 100]);

it('treats exactly 300 lines as a warning and not an error', function () {
    $found = runRule(FileLength::options(), ['Actions/Edge.php' => fileOfLines(300)]);

    expect($found[0]->severity)->toBe(Severity::Warning);
});

it('ignores files outside the CAP 1 directories by default', function () {
    expect(runRule(FileLength::options(), ['database/migrations/Big.php' => fileOfLines(400), 'Models/Big.php' => fileOfLines(400)]))->toBe([]);
});

it('takes its scope and limits from options', function () {
    $files = ['Models/Big.php' => fileOfLines(60)];

    expect(runRule(FileLength::options(target: 10, hard: 50, scope: ['Models']), $files)[0]->severity)->toBe(Severity::Error)
        ->and(runRule(FileLength::options(scope: ['Models']), $files))->toBe([]);
});

it('does not use git', function () {
    $source = file_get_contents(dirname(__DIR__, 3).'/Rules/FileLength.php');

    expect($source)->not->toContain('git ');
});

it('rejects invalid options naming the rule and the option', function (array $options, string $option) {
    expect(fn () => FileLength::options(...$options))
        ->toThrow(InvalidRuleConfigurationException::class, "[file-length] option [{$option}]");
})->with([
    'zero target' => [['target' => 0], 'target'],
    'negative hard' => [['hard' => -5], 'hard'],
    'target above hard' => [['target' => 400, 'hard' => 300], 'target'],
    'empty scope' => [['scope' => []], 'scope'],
]);
