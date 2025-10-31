<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Security;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Tourze\PHPUnitBase\TestCaseHelper;

/**
 * 检查 AccessTokenHandlerInterface 实现的测试用例必须直接继承\Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase
 *
 * @implements Rule<InClassNode>
 */
class AccessTokenHandlerTestMustInheritAbstractIntegrationTestCaseRule implements Rule
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

        // 排除路径中包含 symfony-testing-framework 的文件
        $fileName = $scope->getFile();
        if (str_contains($fileName, 'symfony-testing-framework')) {
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

        // 检查被覆盖的类是否实现了AccessTokenHandlerInterface
        if (!$this->implementsAccessTokenHandlerInterface($coversClass, $scope)) {
            return [];
        }

        // 检查测试类是否直接继承AbstractIntegrationTestCase
        if (!$this->inheritsFromAbstractIntegrationTestCase($classReflection)) {
            return [
                RuleErrorBuilder::message(sprintf(
                    '测试类 %s 测试的是AccessTokenHandlerInterface实现 %s，但没有直接继承 \Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase。',
                    $classReflection->getName(),
                    $coversClass
                ))
                    ->identifier('accessTokenHandlerTest.mustInheritAbstractIntegrationTestCase')
                    ->tip('AccessTokenHandlerInterface实现的测试必须直接继承 \Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase，而不是使用其他测试基类。')
                    ->tip('参考示例：class YourAccessTokenHandlerTest extends AbstractIntegrationTestCase')
                    ->tip('这个规则不允许被忽略')
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

    private function implementsAccessTokenHandlerInterface(string $className, Scope $scope): bool
    {
        try {
            $classReflection = $scope->getClassReflection();
            if (null === $classReflection) {
                return false;
            }

            $coveredClassType = new ObjectType($className);
            $accessTokenHandlerType = new ObjectType(AccessTokenHandlerInterface::class);

            return $accessTokenHandlerType->isSuperTypeOf($coveredClassType)->yes();
        } catch (\Throwable) {
            return false;
        }
    }

    private function inheritsFromAbstractIntegrationTestCase(ClassReflection $classReflection): bool
    {
        // 检查直接继承
        foreach ($classReflection->getParents() as $parent) {
            if ('Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase' === $parent->getName()) {
                return true;
            }
        }

        return false;
    }
}
