<?php

use Studeo\Check\Exceptions\InvalidRuleConfigurationException;
use Studeo\Check\Rules\Laravel\NoDtoInLivewireState;

const LIVEWIRE_USES = "use Livewire\\Component;\nuse Livewire\\Form;\nuse Acme\\Dto\\InputData;\nuse Acme\\Dto\\OutputData;\nuse Acme\\Blog\\Data\\PostData;\nuse Acme\\Blog\\Data\\CreatePostInput;\nuse Acme\\Blog\\Support\\Plain;\n";

function livewireDtoFiles(): array
{
    return [
        'Data/CreatePostInput.php' => phpFile('final readonly class CreatePostInput extends InputData {}', 'Acme\\Blog\\Data', LIVEWIRE_USES),
        'Data/PostData.php' => phpFile('final readonly class PostData extends OutputData {}', 'Acme\\Blog\\Data', LIVEWIRE_USES),
        'Support/Plain.php' => phpFile('final class Plain {}', 'Acme\\Blog\\Support'),
        'Support/WeirdInput.php' => phpFile('final readonly class WeirdInput extends InputData {}', 'Acme\\Blog\\Support', LIVEWIRE_USES),
    ];
}

function livewireFile(string $path, string $body): array
{
    return [$path => phpFile($body, 'Acme\\Blog\\Livewire', LIVEWIRE_USES.'use Acme\\Blog\\Support\\WeirdInput;'."\n")];
}

it('flags a public DTO-typed property on a component and on a Form', function (string $type) {
    $component = livewireFile('Livewire/PostEditor.php', "class PostEditor extends Component\n{\n    public {$type} \$post;\n}");
    $form = livewireFile('Livewire/Forms/PostForm.php', "class PostForm extends Form\n{\n    public {$type} \$post;\n}");

    foreach ([$component, $form] as $files) {
        $found = runRule(NoDtoInLivewireState::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), [...livewireDtoFiles(), ...$files]);

        expect($found)->toHaveCount(1)
            ->and($found[0]->rule)->toBe('no-dto-in-livewire-state')
            ->and($found[0]->subject)->toEndWith('::$post')
            ->and($found[0]->message)->toContain('snake_case')->toContain('toInput()');
    }
})->with([
    'plain Data' => ['PostData'],
    'plain Input' => ['CreatePostInput'],
    'nullable' => ['?PostData'],
    'union' => ['PostData|string'],
    'null union' => ['null|CreatePostInput'],
    'full name' => ['\\Acme\\Blog\\Data\\PostData'],
    'extends InputData outside Data' => ['WeirdInput'],
]);

it('flags a promoted public constructor property', function () {
    $files = livewireFile('Livewire/PostEditor.php', "class PostEditor extends Component\n{\n    public function __construct(public PostData \$post, public string \$title = '') {}\n}");

    expect(array_column(runRule(NoDtoInLivewireState::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), [...livewireDtoFiles(), ...$files]), 'subject'))->toBe(['PostEditor::$post']);
});

it('reports one finding per property', function () {
    $files = livewireFile('Livewire/PostEditor.php', "class PostEditor extends Component\n{\n    public PostData \$a, \$b;\n    public ?CreatePostInput \$c = null;\n}");

    expect(runRule(NoDtoInLivewireState::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), [...livewireDtoFiles(), ...$files]))->toHaveCount(3);
});

it('passes plain state, arrays, private, protected and static DTO properties', function () {
    $body = "class PostEditor extends Component\n{\n    public string \$title = '';\n    public ?int \$count = null;\n    public array \$items = [];\n    public Plain \$plain;\n    private PostData \$hidden;\n    protected CreatePostInput \$guarded;\n    public static PostData \$shared;\n}";

    expect(runRule(NoDtoInLivewireState::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), [...livewireDtoFiles(), ...livewireFile('Livewire/PostEditor.php', $body)]))->toBe([]);
});

it('checks the anonymous class of a multi-file component', function () {
    $files = livewireFile('Livewire/⚡editor/editor.php', "new class extends Component\n{\n    public PostData \$post;\n}\n;");
    $found = runRule(NoDtoInLivewireState::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), [...livewireDtoFiles(), ...$files]);

    expect($found)->toHaveCount(1)
        ->and($found[0]->subject)->toBe('class@anonymous::$post')
        ->and($found[0]->file)->toBe('modules/Blog/Livewire/⚡editor/editor.php');
});

it('treats a Data class of another module as a DTO and a DTO outside Data by its parent', function () {
    $other = livewireFile('Livewire/PostEditor.php', "class PostEditor extends Component\n{\n    public \\Acme\\Shop\\Data\\OrderData \$order;\n    public \\Acme\\Shop\\Models\\Order \$model;\n}");

    expect(array_column(runRule(NoDtoInLivewireState::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), $other, ['Acme\\Blog\\', 'Acme\\Shop\\']), 'subject'))->toBe(['PostEditor::$order']);
});

it('does not treat a Data-namespace class that extends neither base as a DTO without namespaceRoots', function () {
    $other = livewireFile('Livewire/PostEditor.php', "class PostEditor extends Component\n{\n    public \\Acme\\Shop\\Data\\OrderData \$order;\n}");

    expect(runRule(NoDtoInLivewireState::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), $other))->toBe([]);
});

it('only looks at Livewire by default and takes a scope', function () {
    $body = "class PostEditor extends Component\n{\n    public PostData \$post;\n}";
    $elsewhere = livewireFile('Services/PostEditor.php', $body);

    expect(runRule(NoDtoInLivewireState::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE), [...livewireDtoFiles(), ...$elsewhere]))->toBe([])
        ->and(runRule(NoDtoInLivewireState::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE, scope: ['Services']), [...livewireDtoFiles(), ...$elsewhere]))->toHaveCount(1);
});

it('rejects an empty scope naming the rule and the option', function () {
    expect(fn () => NoDtoInLivewireState::options(inputBase: DTO_INPUT_BASE, outputBase: DTO_OUTPUT_BASE, scope: []))->toThrow(InvalidRuleConfigurationException::class, '[no-dto-in-livewire-state] option [scope]');
});

it('rejects a non-class-name inputBase and outputBase naming rule and option', function (string $invalid) {
    expect(fn () => NoDtoInLivewireState::options(inputBase: $invalid, outputBase: DTO_OUTPUT_BASE))
        ->toThrow(InvalidRuleConfigurationException::class, '[no-dto-in-livewire-state] option [inputBase]')
        ->and(fn () => NoDtoInLivewireState::options(inputBase: DTO_INPUT_BASE, outputBase: $invalid))
        ->toThrow(InvalidRuleConfigurationException::class, '[no-dto-in-livewire-state] option [outputBase]');
})->with([
    'empty' => [''],
    'leading backslash' => ['\\Acme\\Dto\\InputData'],
    'trailing backslash' => ['Acme\\Dto\\InputData\\'],
    'double backslash' => ['Acme\\\\InputData'],
    'digit first' => ['Acme\\1InputData'],
]);
