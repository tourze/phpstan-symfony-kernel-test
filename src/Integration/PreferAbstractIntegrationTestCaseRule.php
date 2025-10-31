<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Integration;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * 推荐集成测试使用 AbstractIntegrationTestCase 而不是 KernelTestCase
 *
 * @implements Rule<Class_>
 */
class PreferAbstractIntegrationTestCaseRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_ || !$node->name) {
            return [];
        }

        $className = $scope->getClassReflection()?->getName();
        if (!$className || !str_ends_with($className, 'Test')) {
            return [];
        }

        // 跳过抽象类
        if ($node->isAbstract()) {
            return [];
        }

        $errors = [];

        // 检查是否继承自 KernelTestCase 但使用了集成测试功能
        if ($this->extendsKernelTestCase($scope) && $this->usesIntegrationFeatures($node)) {
            $errors[] = RuleErrorBuilder::message(
                '测试类继承 KernelTestCase 但使用了集成测试功能。建议继承 AbstractIntegrationTestCase 以获得更好的资源管理'
            )->tip('AbstractIntegrationTestCase 提供自动数据库清理、服务访问和 InterfaceStubTrait')->build();
        }

        // 检查是否应该使用 InterfaceStubTrait 但没有使用
        if ($this->shouldUseInterfaceStubTrait($node) && !$this->usesInterfaceStubTrait($node)) {
            $errors[] = RuleErrorBuilder::message(
                '测试类创建接口实现。建议使用 InterfaceStubTrait 或继承 AbstractIntegrationTestCase'
            )->tip('这可以消除代码重复并提供标准接口实现')->build();
        }

        return $errors;
    }

    private function extendsKernelTestCase(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();
        if (!$classReflection || !$classReflection->getParentClass()) {
            return false;
        }

        $parentName = $classReflection->getParentClass()->getName();

        return 'Symfony\Bundle\FrameworkBundle\Test\KernelTestCase' === $parentName;
    }

    private function usesIntegrationFeatures(Class_ $class): bool
    {
        // 检查是否使用了集成测试功能
        $integrationKeywords = [
            'entityManager',
            'getContainer',
            'database',
            'repository',
            'doctrine',
            'cleanDatabase',
            'getService',
        ];

        $visitor = new class($integrationKeywords) extends NodeVisitorAbstract {
            private array $keywords;

            public bool $found = false;

            public function __construct(array $keywords)
            {
                $this->keywords = $keywords;
            }

            public function enterNode(Node $node): void
            {
                if ($this->found) {
                    return;
                }

                $text = '';
                if ($node instanceof Node\Expr\PropertyFetch && $node->name instanceof Node\Identifier) {
                    $text = $node->name->toString();
                } elseif ($node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier) {
                    $text = $node->name->toString();
                }

                foreach ($this->keywords as $keyword) {
                    if (str_contains(strtolower($text), strtolower($keyword))) {
                        $this->found = true;
                        break;
                    }
                }
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse([$class]);

        return $visitor->found;
    }

    private function shouldUseInterfaceStubTrait(Class_ $class): bool
    {
        // 检查是否有接口实现模式
        return $this->hasAnonymousClassWithInterfaces($class)
               || $this->hasCommonMockMethods($class);
    }

    private function hasAnonymousClassWithInterfaces(Class_ $class): bool
    {
        $visitor = new class extends NodeVisitorAbstract {
            public bool $found = false;

            public function enterNode(Node $node): void
            {
                if ($node instanceof Node\Expr\New_
                    && $node->class instanceof Class_
                    && $node->class->implements) {
                    $this->found = true;
                }
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse([$class]);

        return $visitor->found;
    }

    private function hasCommonMockMethods(Class_ $class): bool
    {
        $commonMockMethods = ['createMockUser', 'createUserMock', 'createTestUser'];

        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod) {
                $methodName = $stmt->name->toString();
                foreach ($commonMockMethods as $mockMethod) {
                    if (str_contains(strtolower($methodName), strtolower($mockMethod))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function usesInterfaceStubTrait(Class_ $class): bool
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\TraitUse) {
                foreach ($stmt->traits as $trait) {
                    if ($trait instanceof Node\Name && str_contains($trait->toString(), 'InterfaceStubTrait')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
