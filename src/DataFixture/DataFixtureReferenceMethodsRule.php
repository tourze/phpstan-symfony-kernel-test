<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\DataFixture;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * 检查 DataFixtures 中 addReference() 和 getReference() 方法的正确使用
 *
 * @implements Rule<MethodCall>
 */
class DataFixtureReferenceMethodsRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall) {
            return [];
        }

        // 检查是否在 DataFixture 类中
        $classReflection = $scope->getClassReflection();
        if (!$classReflection || !$this->isDataFixtureClass($classReflection)) {
            return [];
        }

        // 检查方法名
        if (!$node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->name;
        $errors = [];

        // 检查 addReference 方法
        if ('addReference' === $methodName && $node->var instanceof Variable && 'this' === $node->var->name) {
            $argCount = count($node->args);
            if ($argCount < 2) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        'addReference() 方法需要 2 个参数（引用名称和对象），但只提供了 %d 个参数',
                        $argCount
                    )
                )
                    ->line($node->getStartLine())
                    ->tip('使用方式：$this->addReference(self::USER_REFERENCE, $user)')
                    ->build()
                ;
            } elseif ($argCount > 0) {
                // 检查第一个参数是否使用常量（只检查字符串字面量）
                $firstArg = $node->args[0]->value;
                if ($firstArg instanceof Scalar\String_) {
                    $errors[] = RuleErrorBuilder::message(
                        'addReference() 的第一个参数应该使用类常量（如 self::USER_REFERENCE）而不是字符串字面量'
                    )
                        ->line($node->getStartLine())
                        ->tip('定义常量：public const USER_REFERENCE = \'user-reference\'; 然后使用：$this->addReference(self::USER_REFERENCE, $user)')
                        ->build()
                    ;
                }
            }
        }

        // 检查 getReference 方法
        if ('getReference' === $methodName && $node->var instanceof Variable && 'this' === $node->var->name) {
            $argCount = count($node->args);
            if ($argCount < 2) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        'getReference() 方法需要 2 个参数（引用名称和期望的类类型），但只提供了 %d 个参数',
                        $argCount
                    )
                )
                    ->line($node->getStartLine())
                    ->tip('使用方式：$this->getReference(UserFixtures::USER_REFERENCE, User::class)')
                    ->build()
                ;
            } elseif ($argCount > 0) {
                // 检查第一个参数是否使用常量（只检查字符串字面量）
                $firstArg = $node->args[0]->value;
                if ($firstArg instanceof Scalar\String_) {
                    $errors[] = RuleErrorBuilder::message(
                        'getReference() 的第一个参数应该使用类常量（如 UserFixtures::USER_REFERENCE）而不是字符串字面量'
                    )
                        ->line($node->getStartLine())
                        ->tip('使用其他 Fixture 的常量：$this->getReference(UserFixtures::USER_REFERENCE, User::class)')
                        ->build()
                    ;
                }

                // 检查第二个参数是否为 ::class 常量（排除变量）
                if ($argCount >= 2) {
                    $secondArg = $node->args[1]->value;
                    // 只检查非变量的情况
                    if (!($secondArg instanceof Variable) && !($secondArg instanceof Node\Expr\PropertyFetch)) {
                        if (!($secondArg instanceof Node\Expr\ClassConstFetch)
                            || !($secondArg->name instanceof Identifier)
                            || 'class' !== $secondArg->name->name) {
                            $errors[] = RuleErrorBuilder::message(
                                'getReference() 的第二个参数必须是类的 ::class 常量（如 User::class）'
                            )
                                ->line($node->getStartLine())
                                ->tip('使用方式：$this->getReference(UserFixtures::USER_REFERENCE, User::class)')
                                ->build()
                            ;
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * 检查是否为 DataFixture 类
     */
    private function isDataFixtureClass(ClassReflection $classReflection): bool
    {
        // 检查是否继承了 Doctrine\Bundle\FixturesBundle\Fixture
        if ($classReflection->isSubclassOf('Doctrine\Bundle\FixturesBundle\Fixture')) {
            return true;
        }

        // 检查命名空间是否包含 DataFixtures
        return str_contains($classReflection->getName(), 'DataFixtures');
    }
}
