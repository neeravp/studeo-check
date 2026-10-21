<?php

namespace Studeo\Check\Tests\Support;

/**
 * Single-file maps for the rule tests that build the same file around different bodies.
 */
final class RuleFiles
{
    public const MIGRATION_PATH = 'modules/Blog/database/migrations/2026_01_01_000000_create_posts_table.php';

    /**
     * @return array<string, string>
     */
    public static function action(string $declaration, string $uses = ''): array
    {
        return ['Actions/CreatePostAction.php' => phpFile($declaration, uses: $uses)];
    }

    public static function threeIfs(string $wrapper = '%s'): string
    {
        return sprintf($wrapper, 'if ($a) { return 1; } elseif ($b) { return 2; } if ($c) { return 3; }');
    }

    /**
     * @return array<string, string>
     */
    public static function migration(string $body, string $uses = ''): array
    {
        return ['database/migrations/2026_01_01_000000_create_posts_table.php' => phpFile("return new class extends Migration { public function up(): void { {$body} } };", 'Studeo\\Blog', "use Illuminate\\Database\\Migrations\\Migration;\n{$uses}")];
    }
}
