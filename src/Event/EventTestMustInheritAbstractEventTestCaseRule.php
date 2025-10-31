<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Event;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Contracts\EventDispatcher\Event;
use Tourze\PHPUnitBase\TestCaseHelper;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * 如果测试目标是一个Event类（是\Symfony\Contracts\EventDispatcher\Event的子类），
 * 那么这个测试用例就必须直接继承\SymfonyTestingFramework\Test\AbstractEventTestCase。
 *
 * @implements Rule<InClassNode>
 */
class EventTestMustInheritAbstractEventTestCaseRule implements Rule
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

        // 3. 检查被覆盖的类是否继承了 Event
        if (!$coveredClassReflection->isSubclassOf(Event::class)) {
            return [];
        }

        // 4. 检查测试类是否直接继承了 AbstractEventTestCase
        $parentClass = $classReflection->getParentClass();
        if (!$parentClass || AbstractEventTestCase::class !== $parentClass->getName()) {
            return [
                RuleErrorBuilder::message(sprintf(
                    '测试类 %s 测试的是 Event 类 %s，但没有直接继承 %s。',
                    $classReflection->getName(),
                    $coversClass,
                    AbstractEventTestCase::class
                ))
                    ->identifier('event.eventTest.mustInheritAbstractEventTestCase')
                    ->tip('Event 的测试必须直接继承 ' . AbstractEventTestCase::class . ' 以使用预设的测试环境和辅助方法。')
                    ->build(),
            ];
        }

        return [];
    }
}
