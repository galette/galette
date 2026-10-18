<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function Safe\preg_match;

/**
 * Rule to ensure caught exceptions are logged
 *
 * A catch block in galette/lib must rethrow, call Logs::exception(),
 * or explain why nothing is logged with a "// no log: reason" comment.
 * A catch without exception variable may also use Analog::log().
 *
 * @implements Rule<Catch_>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class CatchMustLogRule implements Rule
{
    /**
     * Get node type
     */
    public function getNodeType(): string
    {
        return Catch_::class;
    }

    /**
     * Process node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!str_contains($scope->getFile(), '/galette/lib/')) {
            return [];
        }

        $finder = new NodeFinder();

        if ($finder->findFirstInstanceOf($node->stmts, Throw_::class) !== null) {
            return [];
        }

        if (
            $this->hasStaticCall(
                finder: $finder,
                stmts: $node->stmts,
                class: \Galette\Core\Logs::class,
                method: 'exception'
            )
        ) {
            return [];
        }

        if ($node->var === null && $this->hasStaticCall(finder: $finder, stmts: $node->stmts, class: \Analog\Analog::class, method: 'log')) {
            return [];
        }

        foreach ($finder->find($node->stmts, fn(Node $n) => true) as $child) {
            foreach ($child->getComments() as $comment) {
                if (preg_match('#^//\s*no log:#i', $comment->getText())) {
                    return [];
                }
            }
        }

        return [
            RuleErrorBuilder::message(
                'Caught exception must be rethrown or logged with Galette\Core\Logs::exception(); '
                . 'add a "// no log: <reason>" comment if not logging is intended.'
            )
                ->identifier('galette.catchMustLog')
                ->build()
        ];
    }

    /**
     * Look for a static method call
     *
     * @param NodeFinder  $finder Node finder
     * @param array<Node> $stmts  Statements
     * @param string      $class  Fully qualified class name
     * @param string      $method Method name
     */
    private function hasStaticCall(NodeFinder $finder, array $stmts, string $class, string $method): bool
    {
        return $finder->findFirst(
            $stmts,
            fn(Node $n) => $n instanceof StaticCall
                && $n->class instanceof Name
                && $n->class->toString() === $class
                && $n->name instanceof Node\Identifier
                && $n->name->toString() === $method
        ) !== null;
    }
}
