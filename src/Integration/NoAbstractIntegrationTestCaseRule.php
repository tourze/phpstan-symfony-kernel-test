<?php

namespace Tourze\PHPStanSymfonyKernelTest\Integration;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * PHPStan规则：禁止继承AbstractIntegrationTestCase的类设置为abstract
 */
class NoAbstractIntegrationTestCaseRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /** @var InClassNode $node */
        $classReflection = $node->getClassReflection();
        $classNode = $node->getOriginalNode();

        if (!$classNode instanceof Class_) {
            return [];
        }

        // 检查是否为abstract类
        if (!$classNode->isAbstract()) {
            return [];
        }

        // 检查是否继承自AbstractIntegrationTestCase
        if (!$classReflection->isSubclassOf(AbstractIntegrationTestCase::class)) {
            return [];
        }

        $className = $classReflection->getName();

        // 排除AbstractIntegrationTestCase本身
        if (AbstractIntegrationTestCase::class === $className) {
            return [];
        }

        // 允许作为基类存在的 *TestCase 抽象类
        if (str_ends_with($className, 'TestCase')) {
            return [];
        }

        // 检查是否有抽象方法 - 如果有抽象方法，允许类为抽象
        $nativeReflection = $classReflection->getNativeReflection();
        $abstractMethods = $nativeReflection->getMethods(\ReflectionMethod::IS_ABSTRACT);
        if (!empty($abstractMethods)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                '类 %s 继承了 AbstractIntegrationTestCase 但被标记为 abstract。' .
                '集成测试类应该是具体类以确保测试能够正常执行。',
                $classReflection->getName()
            ))
                ->tip('删除这个类，相关用到的类直接继承 ' . AbstractIntegrationTestCase::class)
                ->identifier('tourze.noAbstractIntegrationTestCase')
                ->build(),
        ];
    }
}
