<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Repository;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassNode>
 */
final class RepositoryTestMustHaveFindTestCasesRule implements Rule
{
    private const REQUIRED_TEST_METHODS = [
        'testFindWithExistingIdShouldReturnEntity' => [
            'summary' => '测试查找一个存在的实体。',
            'tip' => <<<'TIP'
                要实现 "testFindWithExistingIdShouldReturnEntity"：
                1. 场景：数据库中存在一个已知 ID 的实体。
                2. 操作：使用该 ID 调用 `find()` 方法。
                3. 预期结果：方法返回对应的实体对象。
                4. 断言：
                   - 断言返回值不为 `null`。
                   - 断言返回的对象是正确的实体（Entity）类的实例。
                   - 断言返回实体的 ID 与查询的 ID 一致。
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

        $classReflection = $node->getClassReflection();

        // This rule only applies to non-abstract classes ending with "RepositoryTest"
        if ($classReflection->isAbstract() || !str_ends_with($classReflection->getName(), 'RepositoryTest')) {
            return [];
        }

        $errors = [];

        foreach (self::REQUIRED_TEST_METHODS as $methodName => $details) {
            if (!$classReflection->hasMethod($methodName)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    '仓库（Repository）的测试类 "%s" 缺少必需的测试用例: "%s" (%s)',
                    $classReflection->getName(),
                    $methodName,
                    $details['summary']
                ))
                    ->tip($details['tip'])
                    ->identifier('repository.test.missingFindTestCase')
                    ->build()
                ;
            }
        }

        return $errors;
    }
}
