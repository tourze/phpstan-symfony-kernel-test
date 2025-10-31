<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Integration;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 检查继承了 AbstractIntegrationTestCase 的测试用例，如果使用了 CoversClass，则不允许直接 new 测试目标。
 *
 * @implements Rule<New_>
 */
class NoDirectInstantiationOfCoveredClassRule implements Rule
{
    public function getNodeType(): string
    {
        return New_::class;
    }

    /**
     * @param New_ $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();

        // 1. 规则仅适用于继承了 AbstractIntegrationTestCase 的类
        if (null === $classReflection || !$classReflection->isSubclassOf(AbstractIntegrationTestCase::class)) {
            return [];
        }

        // 2. 从 #[CoversClass] 注解中获取被测试的类
        $coveredClass = $this->getCoversClass($classReflection);
        if (null === $coveredClass) {
            return [];
        }

        // 3. 获取当前正在实例化的类的 FQCN
        if (!$node->class instanceof Name) {
            // 忽略 new $variable 或 new (expression)() 等情况
            return [];
        }

        $instantiatedType = $scope->resolveTypeByName($node->class);
        if (!$instantiatedType instanceof ObjectType) {
            return [];
        }
        $instantiatedClass = $instantiatedType->getClassName();

        // 4. 如果实例化的类与测试目标类相同，则报错
        if ($instantiatedClass === $coveredClass) {
            return [
                RuleErrorBuilder::message(sprintf(
                    '在集成测试类 %s 中，不应直接实例化其测试目标 %s。',
                    $classReflection->getDisplayName(),
                    $this->getShortClassName($coveredClass)
                ))
                    ->addTip(sprintf('请从容器中获取服务实例: self::getService(%s::class);', $this->getShortClassName($coveredClass)))
                    ->addTip('同时，如果需要Mock这个服务的依赖，应该在 getService 之前就创建好Mock服务，然后注入到服务容器')
                    ->identifier('integrationTest.noDirectInstantiationOfCoveredClass')
                    ->build(),
            ];
        }

        return [];
    }

    private function getCoversClass(ClassReflection $classReflection): ?string
    {
        try {
            $nativeReflection = $classReflection->getNativeReflection();
            $attributes = $nativeReflection->getAttributes(CoversClass::class);

            if (count($attributes) > 0) {
                $arguments = $attributes[0]->getArguments();
                // 参数可以是位置参数（索引0），也可以是命名参数 'className'
                $className = $arguments[0] ?? $arguments['className'] ?? null;
                if (is_string($className)) {
                    return ltrim($className, '\\');
                }
            }
        } catch (\ReflectionException) {
            // 如果反射失败，我们无法确定被覆盖的类
            return null;
        }

        return null;
    }

    private function getShortClassName(string $fullClassName): string
    {
        $parts = explode('\\', $fullClassName);

        return end($parts);
    }
}
