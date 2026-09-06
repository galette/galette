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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use Rector\PhpParser\Node\Value\ValueResolver;
use Rector\PhpParser\Printer\BetterStandardPrinter;
use Rector\Reflection\ReflectionResolver;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Turn positional arguments into named arguments where the name carries meaning.
 *
 * Naming starts at the first argument when the call passes at least MIN_ARGUMENTS
 * arguments, and otherwise at the first boolean or null literal, whose meaning is
 * unreadable at the call site. Every following positional argument is named too,
 * since PHP forbids a positional argument after a named one. Names are resolved
 * from the callee through reflection.
 *
 * When the resulting call would exceed LINE_LENGTH_LIMIT characters, a line break is
 * forced right after the opening parenthesis so the call becomes multi-line. Rector
 * only introduces that single break; php-cs-fixer (method_argument_space with
 * `ensure_fully_multiline`, enabled through @PER-CS) then normalizes the indentation
 * to one argument per line. Run php-cs-fixer after Rector to get the final layout.
 *
 * Deliberately skips:
 *  - short calls with no boolean or null literal to disambiguate;
 *  - first-class callables (`foo(...)`) and argument unpacking (`...$args`);
 *  - variadic callees (e.g. sprintf), whose tail arguments cannot be named;
 *  - callees declaring `@no-named-arguments`, which keep their parameter names out
 *    of their backward compatibility promise (PHPUnit does so on its whole API);
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
        private readonly BetterStandardPrinter $betterStandardPrinter,
        private readonly ValueResolver $valueResolver
    ) {
    }

    /**
     * Get rules definitions
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Use named arguments on calls passing at least ' . self::MIN_ARGUMENTS
                . ' arguments, or a boolean or null literal',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
                        $object->method($a, $b, $c, $d, $e, $f);
                        CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
                        $object->method(first: $a, second: $b, third: $c, fourth: $d, fifth: $e, sixth: $f);
                        CODE_SAMPLE
                ),
                new CodeSample(
                    <<<'CODE_SAMPLE'
                        in_array($value, $array, true);
                        CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
                        in_array($value, $array, strict: true);
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

        if ($this->refusesNamedArguments($reflection)) {
            return null;
        }

        $parametersAcceptor = ParametersAcceptorSelector::combineAcceptors($reflection->getVariants());

        // Variadic tail arguments cannot be named.
        if ($parametersAcceptor->isVariadic()) {
            return null;
        }

        $parameters = $parametersAcceptor->getParameters();

        $startPosition = $this->resolveFirstPositionToName($args);
        if ($startPosition === null) {
            return null;
        }

        $changed = false;
        for ($index = $startPosition, $count = count($args); $index < $count; ++$index) {
            $arg = $args[$index];
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
     * Position of the first argument to name, or null when the call does not qualify.
     *
     * A long call is named as a whole: past MIN_ARGUMENTS arguments, the positions
     * stop being readable. A short one is named only from its first boolean or null
     * literal, a value that says nothing about its own role at the call site.
     *
     * @param Arg[] $args
     */
    private function resolveFirstPositionToName(array $args): ?int
    {
        if (count($args) >= self::MIN_ARGUMENTS) {
            return 0;
        }

        foreach ($args as $position => $arg) {
            if ($arg->name !== null) {
                continue;
            }
            if ($this->valueResolver->isTrueOrFalse($arg->value) || $this->valueResolver->isNull($arg->value)) {
                return $position;
            }
        }

        return null;
    }

    /**
     * Whether the callee keeps its parameter names out of its backward compatibility
     * promise, through the `@no-named-arguments` annotation. Naming an argument there
     * ties the call to a name the callee is free to change, and PHPStan rejects it.
     *
     * PHPUnit carries the annotation on its whole API, mock builders included.
     */
    private function refusesNamedArguments(MethodReflection|FunctionReflection $reflection): bool
    {
        if ($reflection instanceof ExtendedMethodReflection || $reflection instanceof FunctionReflection) {
            return $reflection->acceptsNamedArguments()->no();
        }

        // a bare MethodReflection does not expose the information: assume naming is fine
        return false;
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
