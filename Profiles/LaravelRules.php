<?php

namespace Studeo\Check\Profiles;

use Studeo\Check\Engine\RuleSet;
use Studeo\Check\Rules\Laravel\DtoShape;
use Studeo\Check\Rules\Laravel\Migration;
use Studeo\Check\Rules\Laravel\NoDtoInLivewireState;

/**
 * Laravel convention rules: DTO shape, migration restrictions, and Livewire state.
 */
final class LaravelRules
{
    private function __construct(
        private readonly string $inputBase,
        private readonly string $outputBase,
    ) {}

    public static function make(string $inputBase, string $outputBase): self
    {
        return new self($inputBase, $outputBase);
    }

    public function rules(): RuleSet
    {
        return RuleSet::of(
            DtoShape::options(inputBase: $this->inputBase, outputBase: $this->outputBase),
            Migration::options(),
            NoDtoInLivewireState::options(inputBase: $this->inputBase, outputBase: $this->outputBase),
        );
    }
}
