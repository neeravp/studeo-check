<?php

use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use Studeo\Check\Rules\Helpers\ClassMembers;
use Studeo\Check\Rules\Helpers\TypedMember;

it('extracts public instance properties and public promoted parameters as publicState', function () {
    $sources = checkSource([
        'Subject.php' => <<<'PHP'
<?php

namespace Acme\Pkg;

class Subject
{
    public string $declaredPublic;
    protected string $declaredProtected;
    private string $declaredPrivate;
    public static string $staticPublic;

    public function __construct(
        public int $promotedPublic,
        protected int $promotedProtected,
        private int $promotedPrivate,
        bool $nonPromoted,
    ) {}
}
PHP,
    ]);

    $finder = new NodeFinder;
    $class = $finder->findFirstInstanceOf($sources->files()[0]->ast(), Class_::class);

    expect($class)->not->toBeNull();

    $state = ClassMembers::publicState($class);
    $variables = array_map(fn (TypedMember $m): string => $m->variable, $state);

    expect($variables)->toBe(['declaredPublic', 'promotedPublic'])
        ->and($state[0]->subject)->toBe('Subject::$declaredPublic')
        ->and($state[1]->subject)->toBe('Subject::$promotedPublic');
});

it('returns all constructor parameters as constructorParameters', function () {
    $sources = checkSource([
        'Controller.php' => <<<'PHP'
<?php

namespace Acme\Pkg;

class Controller
{
    public function __construct(
        public int $promotedPublic,
        protected int $promotedProtected,
        private int $promotedPrivate,
        string $regularParam,
    ) {}
}
PHP,
    ]);

    $finder = new NodeFinder;
    $class = $finder->findFirstInstanceOf($sources->files()[0]->ast(), Class_::class);

    expect($class)->not->toBeNull();

    $params = ClassMembers::constructorParameters($class);
    $variables = array_map(fn (TypedMember $m): string => $m->variable, $params);

    expect($variables)->toBe(['promotedPublic', 'promotedProtected', 'promotedPrivate', 'regularParam']);
});

it('labels an anonymous class as class@anonymous and a named class by its short name', function () {
    $sources = checkSource([
        'Anon.php' => <<<'PHP'
<?php

namespace Acme\Pkg;

$anon = new class {};
class NamedClass {}
PHP,
    ]);

    $finder = new NodeFinder;
    /** @var list<Class_> $classes */
    $classes = $finder->find($sources->files()[0]->ast(), fn ($n) => $n instanceof Class_);

    expect(ClassMembers::label($classes[0]))->toBe('class@anonymous')
        ->and(ClassMembers::label($classes[1]))->toBe('NamedClass');
});
