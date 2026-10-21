<?php

namespace Studeo\Check\Tests\Support;

/**
 * Builds the single-file maps the DtoShape rule is run on.
 */
final class DtoShapeFiles
{
    public const USES = "use Acme\\Dto\\InputData;\nuse Acme\\Dto\\OutputData;\nuse Livewire\\Wireable;\n";

    public const INPUT_CLASS = 'final readonly class %s extends InputData { protected function __construct(public string $title) {} public static function rules(): array { return []; } }';

    /**
     * @return array<string, string>
     */
    public static function file(string $declaration, string $name = 'PostData'): array
    {
        return ["Data/{$name}.php" => phpFile($declaration, 'Acme\\Blog\\Data', self::USES)];
    }
}
