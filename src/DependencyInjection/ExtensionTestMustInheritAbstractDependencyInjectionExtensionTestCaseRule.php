<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\DependencyInjection;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Tourze\PHPUnitBase\TestCaseHelper;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * 如果测试用例的测试目标继承了 \Symfony\Component\DependencyInjection\Extension\Extension，
 * 那么我们要求这个测试用例必须继承 \SymfonyTestingFramework\Test\AbstractDependencyInjectionExtensionTestCase
 *
 * @implements Rule<InClassNode>
 */
class ExtensionTestMustInheritAbstractDependencyInjectionExtensionTestCaseRule implements Rule
{
    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return [];
        }

        // 1. 检查是否是测试类
        if (!TestCaseHelper::isTestClass($classReflection->getName())) {
            return [];
        }

        // 2. 获取CoversClass注解
        $coversClass = TestCaseHelper::extractCoverClass($classReflection->getNativeReflection());
        if (null === $coversClass) {
            return [];
        }

        if (!$this->reflectionProvider->hasClass($coversClass)) {
            return [];
        }
        $coveredClassReflection = $this->reflectionProvider->getClass($coversClass);

        // 3. 检查被覆盖的类是否继承了 Extension
        if (!$coveredClassReflection->isSubclassOf(Extension::class)) {
            return [];
        }

        // 4. 检查测试类是否继承了 AbstractDependencyInjectionExtensionTestCase
        if (!$classReflection->isSubclassOf(AbstractDependencyInjectionExtensionTestCase::class)) {
            return [
                RuleErrorBuilder::message(sprintf(
                    '测试类 %s 测试的是 Extension 类 %s，但没有继承 %s。',
                    $classReflection->getName(),
                    $coversClass,
                    AbstractDependencyInjectionExtensionTestCase::class
                ))
                    ->identifier('dependencyInjection.extensionTest.mustInheritAbstractDependencyInjectionExtensionTestCase')
                    ->tip('Extension 的测试必须继承 ' . AbstractDependencyInjectionExtensionTestCase::class . ' 以使用预设的测试环境和辅助方法。')
                    ->build(),
            ];
        }

        return [];
    }
}
