<?php

namespace Studeo\Check\Rules\Helpers;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\LogicalAnd;
use PhpParser\Node\Expr\BinaryOp\LogicalOr;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * Counts `&&`, `||`, `and` and `or` in a body. Closures and arrow functions count; a class
 * declared inside the body is its own subject, so it is not entered.
 */
final class BooleanOperators extends NodeVisitorAbstract
{
    private int $count = 0;

    /**
     * @param  array<int, Node>  $statements
     */
    public static function in(array $statements): int
    {
        $counter = new self;
        (new NodeTraverser($counter))->traverse($statements);

        return $counter->count;
    }

    /**
     * Whether the body is one `return` statement: the shape of a one-line named helper.
     *
     * @param  array<int, Node>  $statements
     */
    public static function isSingleReturn(array $statements): bool
    {
        return count($statements) === 1 && array_values($statements)[0] instanceof Return_;
    }

    public function enterNode(Node $node): ?int
    {
        $this->count += (int) $this->isBooleanOperator($node);

        return $node instanceof ClassLike ? NodeVisitor::DONT_TRAVERSE_CHILDREN : null;
    }

    private function isBooleanOperator(Node $node): bool
    {
        return $node instanceof BooleanAnd || $node instanceof BooleanOr || $node instanceof LogicalAnd || $node instanceof LogicalOr;
    }
}
