<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Integration;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * PHPStan规则：检查继承KernelTestCase的测试类，建议改为继承AbstractIntegrationTestCase
 *
 * 此规则检查所有继承了 \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase 的测试类，
 * 并建议它们改为继承 \Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase，
 * 以简化测试用例的初始化工作。
 *
 * 排除条件：
 * - 跳过路径中包含 "symfony-testing-framework" 的文件
 * - 跳过抽象类和匿名类
 * - 跳过已经继承AbstractIntegrationTestCase的类
 *
 * @implements Rule<Class_>
 */
class UseAbstractIntegrationTestCaseRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_ || $node->isAbstract()) {
            return [];
        }

        // 跳过匿名类
        if (!$node->name) {
            return [];
        }

        $className = $scope->getClassReflection()?->getName();
        if (!$className) {
            return [];
        }

        // 跳过匿名类
        if ($scope->getClassReflection()->isAnonymous()) {
            return [];
        }

        // 跳过匿名类的另一种检查方法
        if (str_contains($className, 'class@anonymous')) {
            return [];
        }

        // 排除路径中包含 symfony-testing-framework 的文件
        $fileName = $scope->getFile();
        if (str_contains($fileName, 'symfony-testing-framework')) {
            return [];
        }

        // 检查是否继承自 KernelTestCase
        if (!$scope->getClassReflection()->isSubclassOf('Symfony\Bundle\FrameworkBundle\Test\KernelTestCase')) {
            return [];
        }

        // 检查是否已经继承自 AbstractIntegrationTestCase
        if ($scope->getClassReflection()->isSubclassOf(AbstractIntegrationTestCase::class)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                '测试类 %s 继承 KernelTestCase 但应该继承 AbstractIntegrationTestCase 以简化测试初始化。' .
                '将 "extends KernelTestCase" 改为 "extends AbstractIntegrationTestCase" 并更新导入。',
                $className
            ))->build(),
        ];
    }
}
