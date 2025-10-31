<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Repository;

use Doctrine\ORM\Mapping as ORM;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassNode>
 */
final class RepositoryTestMustHaveLockingTestCasesRule implements Rule
{
    private const LOCKING_TEST_METHODS = [
        'testFindWithPessimisticWriteLockShouldReturnEntityAndLockRow' => [
            'summary' => '测试悲观写入锁的行为。',
            'tip' => <<<'TIP'
                要实现 "testFindWithPessimisticWriteLockShouldReturnEntityAndLockRow"：
                1. 场景：在一个事务中，使用写锁（`LockMode::PESSIMISTIC_WRITE`）来查询一个实体。
                2. 操作：在事务中调用 `find(\$id, LockMode::PESSIMISTIC_WRITE)`。
                3. 预期结果：方法成功返回实体，并在数据库层面为该行加上排他锁。
                4. 断言：
                   - 断言返回的对象是正确的实体（Entity）类的实例。
                   - 测试需要被包裹在一个事务中（例如 `$em->transactional(...)`）。
                TIP,
        ],
        'testFindWithOptimisticLockWhenVersionMismatchesShouldThrowExceptionOnFlush' => [
            'summary' => '测试乐观锁在版本不匹配时的失败情况。',
            'tip' => <<<'TIP'
                要实现 "testFindWithOptimisticLockWhenVersionMismatchesShouldThrowExceptionOnFlush"：
                1. 场景：加载一个实体，然后模拟一次外部更新（使其版本号增加），接着尝试 `flush()` 这个已过时的实体。
                2. 操作：
                   a. 查找一个实体。
                   b. 使用独立的 DBAL 连接或查询，直接在数据库中更新该实体的版本号。
                   c. 修改步骤 a 中加载的实体对象的某个属性。
                   d. 调用 `flush()`。
                3. 预期结果：`flush()` 操作因版本冲突而失败，并抛出 `OptimisticLockException`。
                4. 断言：
                   - 使用 `self::expectException(OptimisticLockException::class)` 来断言抛出了正确的异常。
                TIP,
        ],
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof InClassNode) {
            return [];
        }

        $testClassReflection = $node->getClassReflection();

        if ($testClassReflection->isAbstract() || !str_ends_with($testClassReflection->getName(), 'RepositoryTest')) {
            return [];
        }

        $repositoryClassName = $this->getRepositoryClassNameFromCoversAttribute($node);
        if (null === $repositoryClassName || !class_exists($repositoryClassName)) {
            return [];
        }

        try {
            $nativeRepoReflection = new \ReflectionClass($repositoryClassName);
            if (!$nativeRepoReflection->isSubclassOf('Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository')) {
                return [];
            }

            $entityClassName = $this->getEntityClassName($nativeRepoReflection);
            if (null === $entityClassName || !class_exists($entityClassName)) {
                return [];
            }

            if (!$this->entityHasVersionLock($entityClassName)) {
                return [];
            }
        } catch (\ReflectionException $e) {
            return [];
        }

        $errors = [];
        foreach (self::LOCKING_TEST_METHODS as $methodName => $details) {
            if (!$testClassReflection->hasMethod($methodName)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    '仓库（Repository）的测试类 "%s" 缺少并发锁相关的测试用例 "%s"，因为其管理的实体 "%s" 使用了 `#[Version]` 注解。',
                    $testClassReflection->getName(),
                    $methodName,
                    $entityClassName
                ))
                    ->tip($details['tip'])
                    ->identifier('repository.test.missingLockingTestCase')
                    ->build()
                ;
            }
        }

        return $errors;
    }

    private function getRepositoryClassNameFromCoversAttribute(InClassNode $classNode): ?string
    {
        $classStmt = $classNode->getOriginalNode();
        foreach ($classStmt->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                if ('PHPUnit\Framework\Attributes\CoversClass' === $attribute->name->toString()) {
                    if (count($attribute->args) > 0) {
                        $argValue = $attribute->args[0]->value;
                        if ($argValue instanceof Node\Expr\ClassConstFetch) {
                            if ($argValue->class instanceof Node\Name) {
                                return $argValue->class->toString();
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    private function getEntityClassName(\ReflectionClass $repositoryReflection): ?string
    {
        $docComment = $repositoryReflection->getDocComment();
        if (false === $docComment) {
            return null;
        }

        $classRegex = '[\w]+';

        if (preg_match('/@extends\s+ServiceEntityRepository<(' . $classRegex . ')>/', $docComment, $matches)) {
            return $this->resolveClassName($matches[1], $repositoryReflection);
        }

        if (preg_match('/@method\s+(' . $classRegex . ')(?:\|null)?\s+find/', $docComment, $matches)) {
            return $this->resolveClassName($matches[1], $repositoryReflection);
        }

        return null;
    }

    private function resolveClassName(string $className, \ReflectionClass $contextClass): ?string
    {
        $className = ltrim($className, '\\');
        if (class_exists($className)) {
            return $className;
        }

        $namespace = $contextClass->getNamespaceName();
        $entityNamespace = str_replace('\Repository', '\Entity', $namespace);
        $potentialFqcn = $entityNamespace . '\\' . $className;

        if (class_exists($potentialFqcn)) {
            return $potentialFqcn;
        }

        return null;
    }

    private function entityHasVersionLock(string $entityClassName): bool
    {
        $entityReflection = new \ReflectionClass($entityClassName);
        foreach ($entityReflection->getProperties() as $property) {
            $attributes = $property->getAttributes(ORM\Version::class);
            if (!empty($attributes)) {
                return true;
            }
        }

        return false;
    }
}
