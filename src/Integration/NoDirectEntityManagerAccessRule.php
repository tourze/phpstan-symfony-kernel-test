<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Integration;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 检查是否直接访问 EntityManager 属性
 *
 * @implements Rule<PropertyFetch>
 */
class NoDirectEntityManagerAccessRule implements Rule
{
    public function getNodeType(): string
    {
        return PropertyFetch::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof PropertyFetch) {
            return [];
        }

        // 检查是否在继承 AbstractIntegrationTestCase 的类中
        $classReflection = $scope->getClassReflection();
        if (!$classReflection || !$classReflection->isSubclassOf(AbstractIntegrationTestCase::class)) {
            return [];
        }

        // 检查是否访问 entityManager 属性
        if ($node->name instanceof Node\Identifier && 'entityManager' === $node->name->name) {
            // 检查是否是 $this->entityManager
            if ($node->var instanceof Node\Expr\Variable && 'this' === $node->var->name) {
                return [
                    RuleErrorBuilder::message(
                        '使用 getEntityManager() 方法代替直接访问 $this->entityManager'
                    )->build(),
                ];
            }
        }

        return [];
    }
}
