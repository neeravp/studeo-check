<?php

namespace Studeo\Check\Rules\Helpers;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * How deep control blocks (if, loops, try, switch) nest in one body. A branch (else, elseif,
 * catch, finally, and an `else if` written as two words) stays at the level of its block. A
 * closure, an arrow function or a class declared in the body is its own subject, so it is not
 * entered.
 */
final class BlockDepth extends NodeVisitorAbstract
{
    private int $depth = 0;

    private int $deepest = 0;

    /**
     * @param  array<int, Node>  $statements
     */
    public static function in(array $statements): int
    {
        $measure = new self;
        (new NodeTraverser($measure))->traverse($statements);

        return $measure->deepest;
    }

    public function enterNode(Node $node): ?int
    {
        $this->depth += (int) $this->isBlock($node);
        $this->deepest = max($this->deepest, $this->depth);

        return $this->isSeparateSubject($node) ? NodeVisitor::DONT_TRAVERSE_CHILDREN : null;
    }

    public function leaveNode(Node $node): null
    {
        $this->depth -= (int) $this->isBlock($node);

        return null;
    }

    private function isBlock(Node $node): bool
    {
        return $this->isControl($node) && ! $this->continuesChain($node);
    }

    private function isControl(Node $node): bool
    {
        return $node instanceof If_ || $node instanceof Foreach_ || $node instanceof For_ || $node instanceof While_
            || $node instanceof Do_ || $node instanceof TryCatch || $node instanceof Switch_;
    }

    private function continuesChain(Node $node): bool
    {
        return $node instanceof If_ && $this->isOnlyStatementOfElse($node->getAttribute('parent'));
    }

    private function isOnlyStatementOfElse(?Node $parent): bool
    {
        return $parent instanceof Else_ && count($parent->stmts) === 1;
    }

    private function isSeparateSubject(Node $node): bool
    {
        return $node instanceof Closure || $node instanceof ArrowFunction || $node instanceof ClassLike;
    }
}
