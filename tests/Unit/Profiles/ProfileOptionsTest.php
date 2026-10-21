<?php

use Studeo\Check\Profiles\ActionRules;
use Studeo\Check\Profiles\Cap1Rules;
use Studeo\Check\Profiles\LaravelRules;
use Studeo\Check\Rules\ConditionExtraction;
use Studeo\Check\Rules\FileLength;
use Studeo\Check\Rules\Laravel\ActionAuthorization;
use Studeo\Check\Rules\Laravel\ActionShape;
use Studeo\Check\Rules\Laravel\DtoShape;
use Studeo\Check\Rules\Laravel\Migration;
use Studeo\Check\Rules\Laravel\NoDtoInLivewireState;
use Studeo\Check\Rules\Laravel\SystemActionUsage;
use Studeo\Check\Rules\MethodComplexity;
use Studeo\Check\Rules\MethodNaming;
use Studeo\Check\Rules\NestingDepth;
use Studeo\Check\Rules\StepCount;

it('can tell differently configured rules apart using toEqual', function () {
    $default = Migration::options();
    $mutated = Migration::options(forbidden: ['x']);

    expect($default)->not->toEqual($mutated);
});

it('pins the default options of Cap1Rules', function () {
    $rules = Cap1Rules::make()->rules()->rules();

    expect($rules)->toEqual([
        MethodComplexity::options(),
        FileLength::options(),
        MethodNaming::options(),
        StepCount::options(),
        NestingDepth::options(),
        ConditionExtraction::options(),
    ]);
});

it('pins the options of ActionRules with and without attribution hint', function () {
    $withHint = ActionRules::make('Acme\\Attr', 'a hint')->rules()->rules();

    expect($withHint)->toEqual([
        ActionShape::options(),
        SystemActionUsage::options(systemAttribute: 'Acme\\Attr'),
        ActionAuthorization::options(systemAttribute: 'Acme\\Attr', attributionHint: 'a hint'),
    ]);

    $withoutHint = ActionRules::make('Acme\\Attr')->rules()->rules();
    expect($withoutHint[2])->toEqual(
        ActionAuthorization::options(systemAttribute: 'Acme\\Attr'),
    );
});

it('pins the options of LaravelRules', function () {
    $rules = LaravelRules::make('Acme\\Dto\\InputData', 'Acme\\Dto\\OutputData')->rules()->rules();

    expect($rules)->toEqual([
        DtoShape::options(inputBase: 'Acme\\Dto\\InputData', outputBase: 'Acme\\Dto\\OutputData'),
        Migration::options(),
        NoDtoInLivewireState::options(inputBase: 'Acme\\Dto\\InputData', outputBase: 'Acme\\Dto\\OutputData'),
    ]);
});
