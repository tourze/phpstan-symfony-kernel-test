<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\EventSubscriber;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tourze\PHPStanSymfonyKernelTest\Util\EventSubscriberHelper;
use Tourze\PHPUnitBase\TestCaseHelper;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * 强制要求事件订阅测试类继承 AbstractEventSubscriberTestCase 类
 *
 * @implements Rule<InClassNode>
 */
class EventSubscriberTestMustInheritAbstractIntegrationTestCaseRule implements Rule
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

        if (!TestCaseHelper::isTestClass($classReflection->getName())) {
            return [];
        }

        $originalNode = $node->getOriginalNode();
        if (!$originalNode instanceof Class_) {
            return [];
        }

        $coversClass = $this->getCoversClassFromAnnotations($originalNode);
        if (null === $coversClass) {
            return [];
        }

        if (!EventSubscriberHelper::isEventSubscriber($coversClass, $this->reflectionProvider)) {
            return [];
        }

        if (!$this->inheritsFromAbstractTestCase($classReflection)) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Test class %s covers an event subscriber %s but does not directly inherit from ' . AbstractEventSubscriberTestCase::class,
                    $classReflection->getName(),
                    $coversClass
                ))
                    ->identifier('eventSubscriberTest.mustInheritAbstractIntegrationTestCase')
                    ->tip('Tests for event subscribers must directly inherit from ' . AbstractEventSubscriberTestCase::class)
                    ->build(),
            ];
        }

        return [];
    }

    private function getCoversClassFromAnnotations(Class_ $class): ?string
    {
        foreach ($class->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ('PHPUnit\Framework\Attributes\CoversClass' === $attr->name->toString()) {
                    if (isset($attr->args[0]) && $attr->args[0]->value instanceof Node\Expr\ClassConstFetch) {
                        $classConstFetch = $attr->args[0]->value;
                        if ('class' === $classConstFetch->name->toString()) {
                            return $classConstFetch->class->toString();
                        }
                    }
                }
            }
        }

        return null;
    }

    private function inheritsFromAbstractTestCase(ClassReflection $classReflection): bool
    {
        foreach ($classReflection->getParents() as $parent) {
            if (AbstractEventSubscriberTestCase::class === $parent->getName()) {
                return true;
            }
        }

        return false;
    }
}
