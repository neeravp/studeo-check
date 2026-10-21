<?php

use Studeo\Check\Engine\Rule;
use Studeo\Check\Rules\ConditionExtraction;
use Studeo\Check\Rules\MethodComplexity;
use Studeo\Check\Rules\NestingDepth;
use Studeo\Check\Rules\StepCount;

/**
 * The maintainer's own code, parsed and never loaded: tests/fixtures/cap1.
 *
 * @return list<Rule>
 */
function cap1Rules(): array
{
    return [StepCount::options(), NestingDepth::options(), ConditionExtraction::options(), MethodComplexity::options()];
}

/**
 * @return list<string> "rule Subject" for every finding of the four rules over one fixture
 */
function cap1Findings(string $fixture): array
{
    $files = ['Services/Fixture.php' => (string) file_get_contents(__DIR__."/../../fixtures/cap1/{$fixture}.php")];
    $source = checkSource($files);
    $found = array_merge(...array_map(fn (Rule $rule): array => $rule->violations($source), cap1Rules()));

    return array_map(fn ($violation): string => "{$violation->rule} {$violation->subject}", $found);
}

it('gives CreateOrderAction no finding', function () {
    expect(cap1Findings('CreateOrderAction'))->toBe([]);
});

it('gives StripePaymentGatewayBefore exactly three findings', function () {
    $found = cap1Findings('StripePaymentGatewayBefore');
    sort($found);

    expect($found)->toBe([
        'condition-extraction StripePaymentGateway::createCheckoutSession',
        'condition-extraction StripePaymentGateway::refundPayment',
        'nesting-depth StripePaymentGateway::refundPayment',
    ]);
});

it('gives StripePaymentGatewayAfter no finding', function () {
    expect(cap1Findings('StripePaymentGatewayAfter'))->toBe([]);
});
