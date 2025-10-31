<?php

namespace Tourze\PHPStanSymfonyKernelTest\Integration;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * PHPStan 规则：要求所有继承 AbstractIntegrationTestCase 的测试类使用 RunTestsInSeparateProcesses 注解
 *
 * @implements Rule<Class_>
 */
readonly class RequireRunInSeparateProcessRule implements Rule
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param Class_ $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // 获取类名
        if (null === $node->name) {
            return [];
        }

        $className = $node->name->toString();

        // 获取完整的类名
        $namespace = $scope->getNamespace();
        $fullClassName = $namespace ? $namespace . '\\' . $className : $className;

        // 检查类是否存在
        if (!$this->reflectionProvider->hasClass($fullClassName)) {
            return [];
        }

        $classReflection = $this->reflectionProvider->getClass($fullClassName);

        // 检查是否继承自 AbstractIntegrationTestCase
        if (!$this->isIntegrationTestCase($classReflection)) {
            return [];
        }

        // 检查是否有 RunTestsInSeparateProcesses 注解
        if ($this->hasRunTestsInSeparateProcessesAnnotation($node)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                '测试类 %s 必须使用 #[RunTestsInSeparateProcesses] 注解来确保测试隔离',
                $fullClassName
            ))
                ->line($node->getStartLine())
                ->tip('use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;后，在类上添加 #[RunTestsInSeparateProcesses] 注解')
                ->build(),
        ];
    }

    /**
     * 检查类是否继承自 AbstractIntegrationTestCase
     */
    private function isIntegrationTestCase(ClassReflection $classReflection): bool
    {
        // 直接检查是否是 AbstractIntegrationTestCase
        if (AbstractIntegrationTestCase::class === $classReflection->getName()) {
            return false; // 不检查基类本身
        }

        // 检查是否是子类
        if ($classReflection->isSubclassOf(AbstractIntegrationTestCase::class)) {
            return true;
        }

        return false;
    }

    /**
     * 检查类是否有 RunTestsInSeparateProcesses 注解
     */
    private function hasRunTestsInSeparateProcessesAnnotation(Class_ $node): bool
    {
        // 仅检查 PHP 8 属性注解
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attrName = $attr->name->toString();

                // 检查完整类名或短名称
                if ('RunTestsInSeparateProcesses' === $attrName
                    || 'PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses' === $attrName
                    || str_ends_with($attrName, '\RunTestsInSeparateProcesses')) {
                    return true;
                }
            }
        }

        return false;
    }
}
