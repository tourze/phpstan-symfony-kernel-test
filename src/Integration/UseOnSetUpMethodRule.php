<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Integration;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 检查测试类是否使用 onSetUp() 而不是 setUp()
 *
 * @implements Rule<ClassMethod>
 */
class UseOnSetUpMethodRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod) {
            return [];
        }

        // 检查是否在继承 AbstractIntegrationTestCase 的类中
        $classReflection = $scope->getClassReflection();
        if (!$classReflection || !$classReflection->isSubclassOf(AbstractIntegrationTestCase::class)) {
            return [];
        }

        // 检查是否定义了 setUp 方法
        if ('setUp' === $node->name->name) {
            return [
                RuleErrorBuilder::message(
                    '继承 AbstractIntegrationTestCase 时使用 onSetUp() 方法代替 setUp()'
                )->build(),
            ];
        }

        return [];
    }
}
