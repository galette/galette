<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Rector;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Reflection\ParametersAcceptorSelector;
use Rector\PhpParser\Printer\BetterStandardPrinter;
use Rector\Reflection\ReflectionResolver;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Turn positional arguments into named arguments on calls passing many arguments.
 *
 * When a call passes at least MIN_ARGUMENTS arguments, every positional argument is
 * rewritten as a named one, using the parameter names resolved from the callee.
 *
 * When the resulting call would exceed LINE_LENGTH_LIMIT characters, a line break is
 * forced right after the opening parenthesis so the call becomes multi-line. Rector
 * only introduces that single break; php-cs-fixer (method_argument_space with
 * `ensure_fully_multiline`, enabled through @PER-CS) then normalizes the indentation
 * to one argument per line. Run php-cs-fixer after Rector to get the final layout.
 *
 * Deliberately skips:
 *  - calls below the MIN_ARGUMENTS threshold;
 *  - first-class callables (`foo(...)`) and argument unpacking (`...$args`);
 *  - variadic callees (e.g. sprintf), whose tail arguments cannot be named;
 *  - calls whose callee cannot be resolved (no reflection available).
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class AddNamedArgumentsRector extends AbstractRector
{
    /** Minimum number of arguments before naming is applied */
    private const int MIN_ARGUMENTS = 4;

    /**
     * Length of the printed single-line call above which it is broken onto several
     * lines. Kept below the PER-CS 120 soft limit to leave headroom for indentation,
     * assignment and the trailing semicolon (which are not part of the printed call).
     */
    private const int LINE_LENGTH_LIMIT = 100;

    /**
     * Constructor
     */
    public function __construct(
        private readonly ReflectionResolver $reflectionResolver,
        private readonly BetterStandardPrinter $betterStandardPrinter
    ) {
    }

    /**
     * Get rules definitions
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Use named arguments on calls passing at least ' . self::MIN_ARGUMENTS . ' arguments',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
                        $object->method($a, $b, $c, $d, $e, $f);
                        CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
                        $object->method(first: $a, second: $b, third: $c, fourth: $d, fifth: $e, sixth: $f);
                        CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [FuncCall::class, MethodCall::class, StaticCall::class, New_::class];
    }

    /**
     * @param FuncCall|MethodCall|StaticCall|New_ $node
     */
    public function refactor(Node $node): ?Node
    {
        // First-class callable syntax `foo(...)`: nothing to name.
        if ($node->isFirstClassCallable()) {
            return null;
        }

        $args = $node->getArgs();

        if (count($args) < self::MIN_ARGUMENTS) {
            return null;
        }

        $hasPositional = false;
        foreach ($args as $arg) {
            // Argument unpacking (spread) is not compatible with naming the tail.
            if ($arg->unpack) {
                return null;
            }
            if ($arg->name === null) {
                $hasPositional = true;
            }
        }

        if (!$hasPositional) {
            return null;
        }

        $reflection = $this->reflectionResolver->resolveFunctionLikeReflectionFromCall($node);
        if ($reflection === null) {
            return null;
        }

        $parametersAcceptor = ParametersAcceptorSelector::combineAcceptors($reflection->getVariants());

        // Variadic tail arguments cannot be named.
        if ($parametersAcceptor->isVariadic()) {
            return null;
        }

        $parameters = $parametersAcceptor->getParameters();

        $changed = false;
        foreach ($args as $index => $arg) {
            if ($arg->name !== null) {
                continue;
            }
            // Bail out entirely if any positional argument cannot be mapped to a parameter.
            if (!isset($parameters[$index])) {
                return null;
            }
            $arg->name = new Identifier($parameters[$index]->getName());
            $changed = true;
        }

        if (!$changed) {
            return null;
        }

        $this->wrapWhenTooLong($node);

        return $node;
    }

    /**
     * Force a line break after the opening parenthesis when the single-line call is
     * too long. php-cs-fixer turns that break into a fully indented, one-argument-per-line
     * layout afterwards.
     *
     * @param FuncCall|MethodCall|StaticCall|New_ $node
     */
    private function wrapWhenTooLong(Node $node): void
    {
        if (strlen($this->betterStandardPrinter->print($node)) <= self::LINE_LENGTH_LIMIT) {
            return;
        }

        $firstArg = $node->getArgs()[0];

        // An empty comment on the first argument makes the pretty-printer emit a line
        // break right after `(` without leaving any visible comment token behind.
        if ($firstArg->getComments() === []) {
            $firstArg->setAttribute('comments', [new Comment('')]);
        }
    }
}
