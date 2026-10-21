<?php

use Studeo\Check\Engine\PathSourceSet;
use Studeo\Check\Engine\Rule;
use Studeo\Check\Engine\Violation;

/**
 * A throwaway source directory holding the given files, as relative path => contents.
 *
 * @param  array<string, string>  $files
 * @param  list<string>  $namespaceRoots
 */
function checkSource(array $files, array $namespaceRoots = []): PathSourceSet
{
    $base = sys_get_temp_dir().'/check-test-'.bin2hex(random_bytes(6));
    $root = $base.'/modules/Blog';

    foreach ($files as $relative => $contents) {
        $target = "{$root}/{$relative}";
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents($target, $contents);
    }

    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($base)));

    return new PathSourceSet($base, [$root], null, $namespaceRoots);
}

/**
 * @param  array<string, string>  $files
 * @param  list<string>  $namespaceRoots
 * @return array<int, Violation>
 */
function runRule(Rule $rule, array $files, array $namespaceRoots = []): array
{
    return $rule->violations(checkSource($files, $namespaceRoots));
}

/**
 * The tuples a test compares: line, subject and message, so a rule's output is read at a glance.
 *
 * @param  array<int, Violation>  $violations
 * @return array<int, string>
 */
function summarise(array $violations): array
{
    return array_map(fn (Violation $violation): string => "{$violation->file}:{$violation->line} {$violation->subject}", $violations);
}

/**
 * A PHP file in a namespace.
 */
function phpFile(string $body, string $namespace = 'Studeo\\Blog\\Actions', string $uses = ''): string
{
    return "<?php\n\nnamespace {$namespace};\n\n{$uses}\n{$body}\n";
}

function longFile(int $lines): string
{
    return "<?php\n\nnamespace Blog\\Services;\n\nfinal class Long\n{\n".str_repeat("    public int \$a = 1;\n", $lines - 7)."}\n";
}

/**
 * @param  array<string, string>  $files
 */
function createTestSandbox(array $files): string
{
    $dir = sys_get_temp_dir().'/cmd-test-'.bin2hex(random_bytes(6));
    foreach ($files as $relative => $content) {
        $full = "{$dir}/{$relative}";
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0777, true);
        }
        file_put_contents($full, $content);
    }
    register_shutdown_function(fn () => exec('rm -rf '.escapeshellarg($dir)));

    return $dir;
}

const SYSTEM_ATTRIBUTE = 'Acme\\Attributes\\SystemAction';
const SYSTEM_USES = "use Acme\\Attributes\\SystemAction;\nuse Illuminate\\Contracts\\Auth\\Authenticatable;\nuse Illuminate\\Support\\Facades\\Gate;\n";
const SYSTEM_ATTRIBUTION_HINT = 'carry attribution in a #[Context] Input field';
const DTO_INPUT_BASE = 'Acme\\Dto\\InputData';
const DTO_OUTPUT_BASE = 'Acme\\Dto\\OutputData';
