<?php

namespace Studeo\Check\Profiles;

use Studeo\Check\Engine\RuleSet;
use Studeo\Check\Rules\Laravel\ActionAuthorization;
use Studeo\Check\Rules\Laravel\ActionShape;
use Studeo\Check\Rules\Laravel\SystemActionUsage;

/**
 * Action rules: shape, system action usage, and authorization.
 */
final class ActionRules
{
    private function __construct(
        private readonly string $systemAttribute,
        private readonly ?string $attributionHint = null,
    ) {}

    public static function make(string $systemAttribute, ?string $attributionHint = null): self
    {
        return new self($systemAttribute, $attributionHint);
    }

    public function rules(): RuleSet
    {
        return RuleSet::of(
            ActionShape::options(),
            SystemActionUsage::options(systemAttribute: $this->systemAttribute),
            $this->actionAuthorizationRule(),
        );
    }

    private function actionAuthorizationRule(): ActionAuthorization
    {
        if ($this->attributionHint === null) {
            return ActionAuthorization::options(systemAttribute: $this->systemAttribute);
        }

        return ActionAuthorization::options(
            systemAttribute: $this->systemAttribute,
            attributionHint: $this->attributionHint,
        );
    }
}
