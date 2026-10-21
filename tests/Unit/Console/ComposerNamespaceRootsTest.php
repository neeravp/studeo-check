<?php

use Studeo\Check\Console\ComposerNamespaceRoots;

it('returns an empty array when composer.json is missing', function () {
    $dir = createTestSandbox([]);
    $roots = (new ComposerNamespaceRoots)->read($dir);

    expect($roots)->toBe([]);
});

it('returns autoload psr-4 keys then autoload-dev psr-4 keys in file order', function () {
    $json = json_encode([
        'autoload' => [
            'psr-4' => [
                'App\\' => 'app/',
                'Domain\\' => 'src/Domain/',
            ],
        ],
        'autoload-dev' => [
            'psr-4' => [
                'Tests\\' => 'tests/',
                'Workbench\\' => 'workbench/',
            ],
        ],
    ], JSON_PRETTY_PRINT);

    $dir = createTestSandbox(['composer.json' => $json]);
    $roots = (new ComposerNamespaceRoots)->read($dir);

    expect($roots)->toBe(['App\\', 'Domain\\', 'Tests\\', 'Workbench\\']);
});

it('ignores keys without trailing backslash, empty keys, non-array psr-4 and missing autoload section', function () {
    $json = json_encode([
        'name' => 'acme/test',
        'autoload' => [
            'psr-4' => [
                'App' => 'app/',
                '' => 'src/',
                'NotSingle\\\\' => 'invalid/',
            ],
        ],
        'extra' => 'something',
    ], JSON_PRETTY_PRINT);

    $dir = createTestSandbox(['composer.json' => $json]);
    $roots = (new ComposerNamespaceRoots)->read($dir);

    expect($roots)->toBe([]);

    $jsonNonArray = json_encode([
        'autoload' => [
            'psr-4' => 'not-an-array',
        ],
    ]);
    $dir2 = createTestSandbox(['composer.json' => $jsonNonArray]);
    expect((new ComposerNamespaceRoots)->read($dir2))->toBe([]);

    $jsonNoAutoload = json_encode([
        'name' => 'acme/clean',
    ]);
    $dir3 = createTestSandbox(['composer.json' => $jsonNoAutoload]);
    expect((new ComposerNamespaceRoots)->read($dir3))->toBe([]);
});

it('pins that an inner backslash without a trailing backslash is not a root and a multi-segment root is', function () {
    $json = json_encode([
        'autoload' => [
            'psr-4' => [
                'App\\Sub' => 'app/Sub/',
                'Valid\\' => 'src/Valid/',
                'Deep\\Name\\' => 'src/Deep/Name/',
            ],
        ],
    ], JSON_PRETTY_PRINT);

    $dir = createTestSandbox(['composer.json' => $json]);
    $roots = (new ComposerNamespaceRoots)->read($dir);

    expect($roots)->toBe(['Valid\\', 'Deep\\Name\\']);
});

it('deduplicates keys present in both autoload and autoload-dev preserving first occurrence', function () {
    $json = json_encode([
        'autoload' => [
            'psr-4' => [
                'App\\' => 'app/',
                'Shared\\' => 'shared/',
            ],
        ],
        'autoload-dev' => [
            'psr-4' => [
                'Shared\\' => 'tests/shared/',
                'Tests\\' => 'tests/',
            ],
        ],
    ], JSON_PRETTY_PRINT);

    $dir = createTestSandbox(['composer.json' => $json]);
    $roots = (new ComposerNamespaceRoots)->read($dir);

    expect($roots)->toBe(['App\\', 'Shared\\', 'Tests\\']);
});

it('throws RuntimeException when composer.json is not valid JSON', function () {
    $dir = createTestSandbox(['composer.json' => '{ not json']);

    expect(fn () => (new ComposerNamespaceRoots)->read($dir))
        ->toThrow(RuntimeException::class, 'File [composer.json] is not valid JSON:');
});

it('throws RuntimeException when composer.json holds a JSON array at the top level', function () {
    $dir = createTestSandbox(['composer.json' => '["autoload"]']);

    expect(fn () => (new ComposerNamespaceRoots)->read($dir))
        ->toThrow(RuntimeException::class, 'File [composer.json] must hold a JSON object.');
});
