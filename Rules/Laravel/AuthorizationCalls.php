<?php

namespace Studeo\Check\Rules\Laravel;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use Studeo\Check\Rules\Helpers\TypeNames;

/**
 * What an Action class does about authorization, found by parsing: whether __invoke takes an
 * Authenticatable, whether the class calls the Gate facade, calls ->authorize() on anything,
 * or calls Gate::forUser(...)->authorize(...), in __invoke or in any helper.
 */
final class AuthorizationCalls
{
    private const AUTHENTICATABLE = 'Illuminate\\Contracts\\Auth\\Authenticatable';

    private const GATES = ['Illuminate\\Support\\Facades\\Gate', 'Gate'];

    public static function takesAuthenticatable(Class_ $class): bool
    {
        $parameters = $class->getMethod('__invoke')->params ?? [];

        return array_filter($parameters, fn (Param $param): bool => in_array(self::AUTHENTICATABLE, TypeNames::of($param->type), true)) !== [];
    }

    public static function callsGate(Class_ $class): bool
    {
        return self::contains($class, self::isGateCall(...));
    }

    public static function callsAuthorize(Class_ $class): bool
    {
        return self::contains($class, self::isAuthorizeCall(...));
    }

    public static function callsGateForUserAuthorize(Class_ $class): bool
    {
        return self::contains($class, self::isForUserAuthorize(...));
    }

    private static function contains(Class_ $class, callable $test): bool
    {
        return (new NodeFinder)->findFirst($class, $test) !== null;
    }

    /**
     * @phpstan-assert-if-true StaticCall $node
     */
    private static function isGateCall(Node $node): bool
    {
        return $node instanceof StaticCall && $node->class instanceof Name && in_array($node->class->toString(), self::GATES, true);
    }

    /**
     * @phpstan-assert-if-true MethodCall|NullsafeMethodCall $node
     */
    private static function isAuthorizeCall(Node $node): bool
    {
        return ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) && $node->name instanceof Identifier && $node->name->toString() === 'authorize';
    }

    private static function isForUserAuthorize(Node $node): bool
    {
        return self::isAuthorizeCall($node) && self::isGateForUser($node->var);
    }

    private static function isGateForUser(Node $node): bool
    {
        return self::isGateCall($node) && $node->name instanceof Identifier && $node->name->toString() === 'forUser';
    }
}
