<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Command;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Tourze\PHPUnitBase\TestCaseHelper;

/**
 * 检查继承了AbstractIntegrationTestCase的测试类中不应该直接new Command对象
 *
 * @implements Rule<New_>
 */
class NoDirectCommandInstantiationInTestRule implements Rule
{
    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof New_) {
            return [];
        }

        // 检查是否在测试类中
        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return [];
        }

        if (!TestCaseHelper::isTestClass($classReflection->getName())) {
            return [];
        }

        // 检查是否继承了AbstractIntegrationTestCase
        if (!$this->inheritsFromAbstractIntegrationTestCase($classReflection)) {
            return [];
        }

        // 获取被实例化的类名
        if (!$node->class instanceof Name) {
            return [];
        }

        $instantiatedClass = $node->class->toString();

        // 解析完整的类名
        if (!str_contains($instantiatedClass, '\\')) {
            // 尝试从当前作用域获取完整类名
            $type = $scope->resolveTypeByName($node->class);
            if ($type instanceof ObjectType) {
                $instantiatedClass = $type->getClassName();
            }
        }

        // 检查是否是Command类
        if (!$this->isCommandClass($instantiatedClass, $scope)) {
            return [];
        }

        // 检查是否是被测试的Command类
        // 为了简化，只要是Command类的实例化都报错
        // 在实际项目中可以更精确地检查是否是测试目标

        return [
            RuleErrorBuilder::message(sprintf(
                '在继承了AbstractIntegrationTestCase的测试类中，不应该直接实例化Command类 %s。应该使用 self::getContainer()->get(%s::class) 从服务容器中获取。',
                $instantiatedClass,
                $this->getShortClassName($instantiatedClass)
            ))
                ->identifier('commandTest.noDirectInstantiation')
                ->tip('使用服务容器可以更好地管理依赖和Mock对象')
                ->tip('示例：$command = self::getContainer()->get(YourCommand::class);')
                ->tip('如需Mock依赖，请在onSetUp()方法中使用: $container->set(\'service.id\', new MockService());')
                ->build(),
        ];
    }

    private function inheritsFromAbstractIntegrationTestCase(ClassReflection $classReflection): bool
    {
        $abstractIntegrationTestCaseType = new ObjectType('Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase');
        $classType = new ObjectType($classReflection->getName());

        return $abstractIntegrationTestCaseType->isSuperTypeOf($classType)->yes();
    }

    private function isCommandClass(string $className, Scope $scope): bool
    {
        try {
            $classType = new ObjectType($className);
            $commandType = new ObjectType('Symfony\Component\Console\Command\Command');

            return $commandType->isSuperTypeOf($classType)->yes();
        } catch (\Throwable) {
            // 如果无法解析类型，尝试通过类名推断
            return str_ends_with($className, 'Command');
        }
    }

    private function getShortClassName(string $fullClassName): string
    {
        $parts = explode('\\', $fullClassName);

        return end($parts);
    }
}
