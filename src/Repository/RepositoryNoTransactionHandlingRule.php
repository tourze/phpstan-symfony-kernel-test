<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Repository;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\VerbosityLevel;
use Tourze\PHPStanSymfonyKernelTest\Util\RepositoryChecker;

/**
 * 检查 Repository 类不应该处理事务
 *
 * @implements Rule<MethodCall>
 */
final class RepositoryNoTransactionHandlingRule implements Rule
{
    /**
     * 事务相关的方法
     */
    private const TRANSACTION_METHODS = [
        'beginTransaction',
        'commit',
        'rollback',
        'rollBack',
        'transactional',
        'setAutoCommit',
        'getTransactionNestingLevel',
        'isTransactionActive',
        'setTransactionIsolation',
    ];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     *
     * @return array<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // 只检查在 Repository 类中的代码
        if (!$scope->isInClass()) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if (null === $classReflection || !RepositoryChecker::isRepositoryClass($classReflection)) {
            return [];
        }

        // 获取方法名
        $methodName = $this->getMethodName($node);
        if (null === $methodName) {
            return [];
        }

        // 检查是否是事务相关方法
        if (!in_array($methodName, self::TRANSACTION_METHODS, true)) {
            return [];
        }

        // 检查调用对象
        $callerType = $scope->getType($node->var);
        $callerTypeString = $callerType->describe(VerbosityLevel::typeOnly());

        // 如果是在 EntityManager 或 Connection 上调用事务方法
        if (str_contains($callerTypeString, 'EntityManager')
            || str_contains($callerTypeString, 'Connection')
            || str_contains($callerTypeString, 'DBAL')) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Repository 类 %s 不应该处理事务，调用了 %s() 方法。',
                    $classReflection->getName(),
                    $methodName
                ))
                    ->tip('事务管理应该在 Service 层或 Controller 层处理，Repository 只负责数据访问')
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * 从 MethodCall 节点获取方法名
     */
    private function getMethodName(MethodCall $node): ?string
    {
        if ($node->name instanceof Node\Identifier) {
            return $node->name->toString();
        }

        return null;
    }
}
