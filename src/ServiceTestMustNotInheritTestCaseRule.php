<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\TestCase;
use Tourze\PHPStanSymfonyKernelTest\Util\ServiceChecker;
use Tourze\PHPUnitBase\TestCaseHelper;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @implements Rule<InClassNode>
 */
readonly class ServiceTestMustNotInheritTestCaseRule implements Rule
{
    public function __construct(private ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        if (!TestCaseHelper::isTestClass($classReflection->getName())) {
            return [];
        }

        $parentClass = $classReflection->getParentClass();
        if (!$parentClass || TestCase::class !== $parentClass->getName()) {
            return [];
        }

        $coveredClassName = TestCaseHelper::extractCoverClass($classReflection->getNativeReflection());
        if (null === $coveredClassName) {
            return [];
        }

        if (!$this->reflectionProvider->hasClass($coveredClassName)) {
            return [];
        }
        $coveredClassReflection = $this->reflectionProvider->getClass($coveredClassName);

        if (!ServiceChecker::isService($coveredClassReflection)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                '测试用例 %s 的测试目标 %s 是一个服务，因此不应直接继承自 %s。',
                $classReflection->getName(),
                $coveredClassName,
                TestCase::class
            ))
                ->tip('服务的测试通常需要依赖注入容器来获取实例和模拟依赖。请考虑继承 ' . AbstractIntegrationTestCase::class)
                ->build(),
        ];
    }
}
