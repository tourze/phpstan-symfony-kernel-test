<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Repository;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * 检查使用 EntityManager::getRepository() 的情况，建议直接注入对应的 Repository 类
 *
 * @implements Rule<MethodCall>
 */
class PreferRepositoryInjectionRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // 只检查 src/ 目录下的代码，不检查测试用例
        $fileName = $scope->getFile();
        if (!str_contains($fileName, '/src/') || str_contains($fileName, '/tests/')) {
            return [];
        }

        // 检查是否是方法调用
        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        // 检查是否调用的是 getRepository 方法
        if ('getRepository' !== $node->name->name) {
            return [];
        }

        // 获取调用对象的类型
        $callerType = $scope->getType($node->var);

        // 检查是否是 EntityManager 或 EntityManagerInterface
        if (!$this->isEntityManager($callerType)) {
            return [];
        }

        // 检查参数
        if (1 !== count($node->args)) {
            return [];
        }

        $arg = $node->args[0];
        if (!$arg instanceof Arg) {
            return [];
        }

        // 检查参数是否是字符串字面量
        if ($arg->value instanceof String_) {
            return [
                RuleErrorBuilder::message(sprintf(
                    '建议直接注入 Repository 类而不是使用 $this->entityManager->getRepository("%s")。请考虑直接注入对应的 Repository。',
                    $arg->value->value
                ))
                    ->tip('建议在构造函数中注入 Repository 而不是使用 EntityManager::getRepository() 方法。')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        // 检查参数是否是 ::class 常量
        if ($arg->value instanceof ClassConstFetch) {
            $className = 'unknown';
            if ($arg->value->class instanceof Node\Name) {
                $className = $arg->value->class->toString();
            }

            return [
                RuleErrorBuilder::message(sprintf(
                    '建议直接注入 Repository 类而不是使用 $this->entityManager->getRepository(%s::class)。请考虑直接注入对应的 Repository。',
                    $className
                ))
                    ->tip('建议在构造函数中注入 Repository 而不是使用 EntityManager::getRepository() 方法。')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    private function isEntityManager(?Type $type): bool
    {
        if (null === $type) {
            return false;
        }

        // 检查是否是 EntityManager 或 EntityManagerInterface
        $entityManagerType = new ObjectType('Doctrine\ORM\EntityManager');
        $entityManagerInterfaceType = new ObjectType('Doctrine\ORM\EntityManagerInterface');

        return $entityManagerType->isSuperTypeOf($type)->yes()
            || $entityManagerInterfaceType->isSuperTypeOf($type)->yes();
    }
}
