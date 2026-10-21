<?php

use Studeo\Check\Engine\PathSourceSet;
use Studeo\Check\Engine\Scope;

it('calculates displayPath inside the base', function () {
    $sources = new PathSourceSet('/var/app', ['/var/app/src']);

    expect($sources->displayPath('/var/app/src/Actions/Run.php'))->toBe('src/Actions/Run.php');
});

it('calculates displayPath outside the base as the absolute path', function () {
    $sources = new PathSourceSet('/var/app', ['/var/app/src']);

    expect($sources->displayPath('/tmp/other/Run.php'))->toBe('/tmp/other/Run.php');
});

it('calculates displayPath when the base path has a trailing slash', function () {
    $sources = new PathSourceSet('/var/app/', ['/var/app/src']);

    expect($sources->displayPath('/var/app/src/Actions/Run.php'))->toBe('src/Actions/Run.php');
});

it('returns files ordered by name and filters by scope', function () {
    $dir = sys_get_temp_dir().'/path-source-test-'.bin2hex(random_bytes(6));
    mkdir("{$dir}/b", 0777, true);
    mkdir("{$dir}/a", 0777, true);
    file_put_contents("{$dir}/b/z.php", "<?php\nclass Z {}");
    file_put_contents("{$dir}/a/m.php", "<?php\nclass M {}");
    file_put_contents("{$dir}/a/a.php", "<?php\nclass A {}");
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($dir)));

    $sources = new PathSourceSet($dir, [$dir]);
    $files = $sources->files();

    expect(array_map(fn ($f) => $f->relativePath, $files))->toBe(['a/a.php', 'a/m.php', 'b/z.php'])
        ->and($sources->basePath())->toBe($dir)
        ->and($sources->roots())->toBe([$dir]);

    $scoped = $sources->files(new Scope(['a']));
    expect(array_map(fn ($f) => $f->relativePath, $scoped))->toBe(['a/a.php', 'a/m.php']);
});

it('sets relativePath relative to base path for individual file roots without scope base', function () {
    $dir = sys_get_temp_dir().'/path-file-test-'.bin2hex(random_bytes(6));
    mkdir("{$dir}/app/Services", 0777, true);
    file_put_contents("{$dir}/app/Services/Busy.php", "<?php\nclass Busy {}");
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($dir)));

    $sources = new PathSourceSet($dir, ["{$dir}/app/Services/Busy.php"]);
    $files = $sources->files();

    expect($files)->toHaveCount(1)
        ->and($files[0]->relativePath)->toBe('app/Services/Busy.php')
        ->and($files[0]->displayPath)->toBe('app/Services/Busy.php');
});

it('keeps A1 behaviour when no scope base is given', function () {
    $dir = sys_get_temp_dir().'/path-a1-test-'.bin2hex(random_bytes(6));
    mkdir("{$dir}/modules/Blog/Actions", 0777, true);
    file_put_contents("{$dir}/modules/Blog/Actions/Post.php", "<?php\nclass Post {}");
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($dir)));

    $sources = new PathSourceSet($dir, ["{$dir}/modules/Blog"]);
    $files = $sources->files();

    expect($files)->toHaveCount(1)
        ->and($files[0]->relativePath)->toBe('Actions/Post.php')
        ->and($files[0]->displayPath)->toBe('modules/Blog/Actions/Post.php');

    $scoped = $sources->files(new Scope(['Actions']));
    expect($scoped)->toHaveCount(1);
});

it('does not match app/Models/Concerns/X.php with Concerns scope when scopeBase is app', function () {
    $dir = sys_get_temp_dir().'/path-concerns-test-'.bin2hex(random_bytes(6));
    mkdir("{$dir}/app/Models/Concerns", 0777, true);
    mkdir("{$dir}/app/Concerns", 0777, true);
    file_put_contents("{$dir}/app/Models/Concerns/X.php", "<?php\nclass X {}");
    file_put_contents("{$dir}/app/Concerns/Y.php", "<?php\nclass Y {}");
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($dir)));

    $sources = new PathSourceSet($dir, ["{$dir}/app"], "{$dir}/app");

    $files = $sources->files();
    expect(array_map(fn ($f) => $f->relativePath, $files))->toBe([
        'Concerns/Y.php',
        'Models/Concerns/X.php',
    ]);

    $concernsScoped = $sources->files(new Scope(['Concerns']));
    expect(array_map(fn ($f) => $f->relativePath, $concernsScoped))->toBe(['Concerns/Y.php']);

    $modelsScoped = $sources->files(new Scope(['Models']));
    expect(array_map(fn ($f) => $f->relativePath, $modelsScoped))->toBe(['Models/Concerns/X.php']);
});

it('handles files outside app when scopeBase is set to app', function () {
    $dir = sys_get_temp_dir().'/path-outside-test-'.bin2hex(random_bytes(6));
    mkdir("{$dir}/app/Services", 0777, true);
    mkdir("{$dir}/routes", 0777, true);
    file_put_contents("{$dir}/app/Services/Order.php", "<?php\nclass Order {}");
    file_put_contents("{$dir}/routes/web.php", "<?php\nreturn [];");
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($dir)));

    // Directory root outside app
    $sourcesDir = new PathSourceSet($dir, ["{$dir}/routes"], "{$dir}/app");
    $filesDir = $sourcesDir->files();
    expect($filesDir)->toHaveCount(1)
        ->and($filesDir[0]->relativePath)->toBe('web.php')
        ->and($filesDir[0]->displayPath)->toBe('routes/web.php');

    // Individual file root outside app
    $sourcesFile = new PathSourceSet($dir, ["{$dir}/routes/web.php"], "{$dir}/app");
    $filesFile = $sourcesFile->files();
    expect($filesFile)->toHaveCount(1)
        ->and($filesFile[0]->relativePath)->toBe('routes/web.php')
        ->and($filesFile[0]->displayPath)->toBe('routes/web.php');
});

it('returns the same classIndex instance twice', function () {
    $dir = sys_get_temp_dir().'/path-index-test-'.bin2hex(random_bytes(6));
    mkdir("{$dir}/src", 0777, true);
    file_put_contents("{$dir}/src/A.php", "<?php\nnamespace Acme\\Pkg;\nclass A {}\n");
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($dir)));

    $sources = new PathSourceSet($dir, ["{$dir}/src"]);

    $first = $sources->classIndex();
    $second = $sources->classIndex();

    expect($first)->toBe($second);
});

it('resolves directoryOf by longest matching prefix and returns the first segment of remainder', function () {
    $sources = new PathSourceSet(
        '/var/app',
        ['/var/app/src'],
        null,
        ['Acme\\', 'Acme\\Blog\\', 'Acme\\Blog\\Sub\\'],
    );

    expect($sources->directoryOf('Acme\\Blog\\Actions\\CreatePost'))->toBe('Actions')
        ->and($sources->directoryOf('Acme\\Blog\\Data\\Posts\\PostData'))->toBe('Data')
        ->and($sources->directoryOf('Acme\\Blog\\Sub\\Services\\Worker'))->toBe('Services')
        ->and($sources->directoryOf('Acme\\Billing\\Invoices\\Charge'))->toBe('Billing')
        ->and($sources->directoryOf('Other\\Package\\Model'))->toBeNull();
});

it('returns null for directoryOf when namespaceRoots is empty', function () {
    $sources = new PathSourceSet('/var/app', ['/var/app/src'], null, []);

    expect($sources->directoryOf('Acme\\Blog\\Actions\\CreatePost'))->toBeNull();
});
