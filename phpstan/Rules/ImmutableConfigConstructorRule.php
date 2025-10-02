<?php

declare(strict_types=1);

namespace Kekke\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClassMethod>
 */
class ImmutableConfigConstructorRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @return list<string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->name->toString() !== '__construct') {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if (!$classReflection instanceof ClassReflection) {
            return [];
        }

        if (!$classReflection->isSubclassOf(\Kekke\Mononoke\Models\ImmutableConfig::class)) {
            return [];
        }

        $hasParentCall = false;
        foreach ($node->getStmts() ?? [] as $stmt) {
            if (
                $stmt instanceof Node\Stmt\Expression &&
                $stmt->expr instanceof Node\Expr\StaticCall &&
                $stmt->expr->class instanceof Node\Name &&
                $stmt->expr->class->toString() === 'parent' &&
                $stmt->expr->name->toString() === '__construct'
            ) {
                $hasParentCall = true;
                break;
            }
        }

        if (!$hasParentCall) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Class %s extends ImmutableConfig but its constructor does not call parent::__construct().',
                    $classReflection->getName()
                ))->build()
            ];
        }

        return [];
    }
}
