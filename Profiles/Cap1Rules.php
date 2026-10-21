<?php

namespace Studeo\Check\Profiles;

use Studeo\Check\Engine\RuleSet;
use Studeo\Check\Rules\ConditionExtraction;
use Studeo\Check\Rules\FileLength;
use Studeo\Check\Rules\MethodComplexity;
use Studeo\Check\Rules\MethodNaming;
use Studeo\Check\Rules\NestingDepth;
use Studeo\Check\Rules\StepCount;

final class Cap1Rules
{
    public static function make(): self
    {
        return new self;
    }

    public function rules(): RuleSet
    {
        return RuleSet::of(
            MethodComplexity::options(),
            FileLength::options(),
            MethodNaming::options(),
            StepCount::options(),
            NestingDepth::options(),
            ConditionExtraction::options(),
        );
    }
}
