<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Command;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Tourze\PHPUnitBase\TestCaseHelper;

/**
 * 检查Command类的测试用例必须使用CommandTester进行测试
 * 这样子可以简化很多逻辑
 *
 * @implements Rule<InClassNode>
 */
class CommandTestMustUseCommandTesterRule implements Rule
{
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

        // 检查是否是测试类
        if (!TestCaseHelper::isTestClass($classReflection->getName())) {
            return [];
        }

        $originalNode = $node->getOriginalNode();
        if (!$originalNode instanceof Class_) {
            return [];
        }

        // 获取CoversClass注解
        $coversClass = $this->getCoversClassFromAnnotations($originalNode);
        if (null === $coversClass) {
            return [];
        }

        // 检查被覆盖的类是否是Command类
        if (!$this->isCommandClass($coversClass, $scope)) {
            return [];
        }

        // 检查测试方法中是否使用了CommandTester
        if (!$this->hasCommandTesterUsage($originalNode)) {
            return [
                RuleErrorBuilder::message(sprintf(
                    '测试类 %s 测试的是Command类 %s，但没有在测试方法中使用 CommandTester。',
                    $classReflection->getName(),
                    $coversClass
                ))
                    ->identifier('commandTest.missingCommandTester')
                    ->tip('Command类的测试应该使用 \Symfony\Component\Console\Tester\CommandTester 来模拟执行命令，测试测试execute方法')
                    ->tip('参考示例：$commandTester = new CommandTester($command); $commandTester->execute([]);')
                    ->build(),
            ];
        }

        return [];
    }

    private function getCoversClassFromAnnotations(Class_ $class): ?string
    {
        foreach ($class->attrGroups as $attrGroup) {
            $result = $this->findCoversClassInGroup($attrGroup);
            if (null !== $result) {
                return $result;
            }
        }

        return null;
    }

    private function findCoversClassInGroup(Node\AttributeGroup $attrGroup): ?string
    {
        foreach ($attrGroup->attrs as $attr) {
            if ('PHPUnit\Framework\Attributes\CoversClass' === $attr->name->toString()) {
                return $this->extractClassFromCoversAttribute($attr);
            }
        }

        return null;
    }

    private function extractClassFromCoversAttribute(Node\Attribute $attr): ?string
    {
        if (!isset($attr->args[0]) || !$attr->args[0]->value instanceof Node\Expr\ClassConstFetch) {
            return null;
        }

        $classConstFetch = $attr->args[0]->value;
        if ('class' === $classConstFetch->name->toString()) {
            return $classConstFetch->class->toString();
        }

        return null;
    }

    private function isCommandClass(string $className, Scope $scope): bool
    {
        try {
            $classReflection = $scope->getClassReflection();
            if (null === $classReflection) {
                return false;
            }

            $coveredClassType = new ObjectType($className);
            $commandType = new ObjectType('Symfony\Component\Console\Command\Command');

            return $commandType->isSuperTypeOf($coveredClassType)->yes();
        } catch (\Throwable) {
            // 如果无法解析类型，尝试通过类名推断
            return str_ends_with($className, 'Command');
        }
    }

    private function hasCommandTesterUsage(Class_ $class): bool
    {
        // 检查类属性中是否有 CommandTester 类型的声明
        if ($this->hasCommandTesterProperty($class)) {
            return true;
        }

        // 检查 onSetUp 方法中是否初始化了 CommandTester
        foreach ($class->getMethods() as $method) {
            if ('onSetUp' === $method->name->toString() && $this->methodUsesCommandTester($method)) {
                return true;
            }
        }

        // 检查测试方法中是否使用了 CommandTester
        foreach ($class->getMethods() as $method) {
            if (!$this->isTestMethod($method->name->toString())) {
                continue;
            }

            if ($this->methodUsesCommandTester($method)) {
                return true;
            }
        }

        return false;
    }

    private function isTestMethod(string $methodName): bool
    {
        return str_starts_with($methodName, 'test') || str_contains($methodName, 'Test');
    }

    private function methodUsesCommandTester(Node\Stmt\ClassMethod $method): bool
    {
        if (null === $method->stmts) {
            return false;
        }

        foreach ($method->stmts as $stmt) {
            if ($this->statementContainsCommandTester($stmt)) {
                return true;
            }
        }

        return false;
    }

    private function statementContainsCommandTester(Node $node): bool
    {
        // 检查 new CommandTester() 的使用
        if ($this->isCommandTesterInstantiation($node)) {
            return true;
        }

        // 检查变量名为 commandTester
        if ($this->isCommandTesterVariable($node)) {
            return true;
        }

        // 递归检查子节点
        return $this->checkChildNodes($node);
    }

    private function checkChildNodes(Node $node): bool
    {
        $visitor = new class($this) extends NodeVisitorAbstract {
            private bool $found = false;

            private CommandTestMustUseCommandTesterRule $rule;

            public function __construct(CommandTestMustUseCommandTesterRule $rule)
            {
                $this->rule = $rule;
            }

            public function enterNode(Node $node): ?Node
            {
                if ($this->rule->isCommandTesterInstantiation($node) || $this->rule->isCommandTesterVariable($node)) {
                    $this->found = true;
                }

                return null;
            }

            public function hasFound(): bool
            {
                return $this->found;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse([$node]);

        return $visitor->hasFound();
    }

    public function isCommandTesterInstantiation(Node $node): bool
    {
        return $node instanceof Node\Expr\New_
            && $node->class instanceof Node\Name
            && $this->isCommandTesterClass($node->class->toString());
    }

    public function isCommandTesterVariable(Node $node): bool
    {
        // 检查本地变量 $commandTester
        if ($node instanceof Node\Expr\Variable && 'commandTester' === $node->name) {
            return true;
        }

        // 检查属性访问 $this->commandTester
        if ($node instanceof Node\Expr\PropertyFetch
            && $node->var instanceof Node\Expr\Variable
            && 'this' === $node->var->name
            && $node->name instanceof Node\Identifier
            && 'commandTester' === $node->name->toString()) {
            return true;
        }

        return false;
    }

    private function isCommandTesterClass(string $className): bool
    {
        return 'CommandTester' === $className
               || 'Symfony\Component\Console\Tester\CommandTester' === $className
               || str_ends_with($className, '\CommandTester');
    }

    private function hasCommandTesterProperty(Class_ $class): bool
    {
        foreach ($class->getProperties() as $property) {
            // 检查属性名是否包含 commandTester
            foreach ($property->props as $prop) {
                if (str_contains(strtolower($prop->name->toString()), 'commandtester')) {
                    return true;
                }
            }

            // 检查属性类型是否是 CommandTester
            if (null !== $property->type) {
                $typeString = $this->getTypeString($property->type);
                if ($this->isCommandTesterClass($typeString)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getTypeString(Node $type): string
    {
        if ($type instanceof Node\Identifier) {
            return $type->toString();
        }

        if ($type instanceof Node\Name) {
            return $type->toString();
        }

        if ($type instanceof Node\IntersectionType && count($type->types) > 0) {
            // 对于交叉类型，检查第一个类型
            return $this->getTypeString($type->types[0]);
        }

        return '';
    }
}
