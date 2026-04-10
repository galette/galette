<?php

/**
 * Copyright © 2003-2026 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Galette\Tests\PHPStan;

use Galette\Entity\AbstractEntity;
use Galette\Entity\Attributes\SkipIdCheck;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Rule to ensure all AbstractEntity child classes define TABLE and PK constants
 * and declare the $id property explicitly
 *
 * To skip the $id property check, add the #[SkipIdCheck] attribute to the class
 *
 * @implements Rule<InClassNode>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RequireEntityConstantRule implements Rule
{
    /**
     * Get node type
     */
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * Process node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        // Skip if not a concrete class (abstract/interface)
        if ($classReflection->isAbstract() || $classReflection->isInterface()) {
            return [];
        }

        // Check if class extends AbstractEntity
        if (!$classReflection->isSubclassOf(AbstractEntity::class)) {
            return [];
        }

        $errors = [];

        // Check TABLE constant
        if (!$this->hasNonEmptyConstant($classReflection, 'TABLE')) {
            $errors[] = RuleErrorBuilder::message(
                sprintf(
                    'Class %s extends AbstractEntity but does not define a non-empty TABLE constant.',
                    $classReflection->getDisplayName()
                )
            )->build();
        }

        // Check PK constant
        if (!$this->hasNonEmptyConstant($classReflection, 'PK')) {
            $errors[] = RuleErrorBuilder::message(
                sprintf(
                    'Class %s extends AbstractEntity but does not define a non-empty PK constant.',
                    $classReflection->getDisplayName()
                )
            )->build();
        }

        // Check $id property (unless explicitly skipped via attribute)
        if (!$this->shouldSkipIdCheck($classReflection) && !$this->hasIdProperty($classReflection)) {
            $errors[] = RuleErrorBuilder::message(
                sprintf(
                    'Class %s extends AbstractEntity but does not explicitly declare the $id property. '
                    . 'Add #[SkipIdCheck] attribute to skip this check.',
                    $classReflection->getDisplayName()
                )
            )->build();
        }

        return $errors;
    }

    /**
     * Has a non empty constant
     */
    private function hasNonEmptyConstant(ClassReflection $classReflection, string $constantName): bool
    {
        if (!$classReflection->hasConstant($constantName)) {
            return false;
        }

        $constant = $classReflection->getConstant($constantName);
        $value = $constant->getValueExpr();

        // Check if the constant value is a non-empty string
        if ($value instanceof Node\Scalar\String_) {
            return $value->value !== '';
        }

        return false;
    }

    /**
     * Check if the class has the #[SkipIdCheck] attribute
     */
    private function shouldSkipIdCheck(ClassReflection $classReflection): bool
    {
        $nativeReflection = $classReflection->getNativeReflection();

        // Get all attributes of the class
        $attributes = $nativeReflection->getAttributes(SkipIdCheck::class);

        // If the SkipIdCheck attribute is present, skip the check
        return count($attributes) > 0;
    }

    /**
     * Check if the class explicitly declares the $id property
     * (not inherited from parent)
     */
    private function hasIdProperty(ClassReflection $classReflection): bool
    {
        // Check if the property exists and is declared in this class
        if (!$classReflection->hasNativeProperty('id')) {
            return false;
        }

        $property = $classReflection->getNativeProperty('id');

        // Ensure the property is declared in this specific class, not inherited
        return $property->getDeclaringClass()->getName() === $classReflection->getName();
    }
}
