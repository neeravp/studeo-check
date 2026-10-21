<?php

use Studeo\Check\Console\CheckCommand;
use Studeo\Check\Engine\RuleSet;
use Studeo\Check\Rules\Laravel\NoDtoInLivewireState;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckCommandNamespaceRootsRuleSet
{
    public function rules(): RuleSet
    {
        return RuleSet::of(NoDtoInLivewireState::options(
            inputBase: 'Acme\\Dto\\InputData',
            outputBase: 'Acme\\Dto\\OutputData',
        ));
    }
}

it('exits 1 and reports no-dto-in-livewire-state when composer.json provides psr-4 roots', function () {
    $dir = createTestSandbox([
        'composer.json' => json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]),
        'app/Data/PostData.php' => "<?php\n\nnamespace App\\Data;\n\nfinal class PostData {}\n",
        'app/Livewire/Editor.php' => "<?php\n\nnamespace App\\Livewire;\n\nuse App\\Data\\PostData;\nuse Livewire\\Component;\n\nclass Editor extends Component\n{\n    public PostData \$post;\n}\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['--rules' => CheckCommandNamespaceRootsRuleSet::class]);

        expect($exit)->toBe(1)
            ->and($tester->getDisplay())->toContain('app/Livewire/Editor.php:10 [no-dto-in-livewire-state]')
            ->and($tester->getDisplay())->toContain('1 error, 0 warnings.');
    } finally {
        chdir($cwd);
    }
});

it('exits 0 with 0 errors and 0 warnings on the same tree without composer.json', function () {
    $dir = createTestSandbox([
        'app/Data/PostData.php' => "<?php\n\nnamespace App\\Data;\n\nfinal class PostData {}\n",
        'app/Livewire/Editor.php' => "<?php\n\nnamespace App\\Livewire;\n\nuse App\\Data\\PostData;\nuse Livewire\\Component;\n\nclass Editor extends Component\n{\n    public PostData \$post;\n}\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['--rules' => CheckCommandNamespaceRootsRuleSet::class]);

        expect($exit)->toBe(0)
            ->and($tester->getDisplay())->toContain('0 errors, 0 warnings.');
    } finally {
        chdir($cwd);
    }
});

it('exits 2 and prints error message on the same tree with an invalid composer.json', function () {
    $dir = createTestSandbox([
        'composer.json' => '{ not json',
        'app/Data/PostData.php' => "<?php\n\nnamespace App\\Data;\n\nfinal class PostData {}\n",
        'app/Livewire/Editor.php' => "<?php\n\nnamespace App\\Livewire;\n\nuse App\\Data\\PostData;\nuse Livewire\\Component;\n\nclass Editor extends Component\n{\n    public PostData \$post;\n}\n",
    ]);

    $cwd = getcwd();
    chdir($dir);
    try {
        $tester = new CommandTester(new CheckCommand);
        $exit = $tester->execute(['--rules' => CheckCommandNamespaceRootsRuleSet::class], ['capture_stderr_separately' => true]);

        expect($exit)->toBe(2)
            ->and($tester->getErrorOutput())->toContain('File [composer.json] is not valid JSON:');
    } finally {
        chdir($cwd);
    }
});
