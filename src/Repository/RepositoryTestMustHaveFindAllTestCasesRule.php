<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Repository;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassNode>
 */
final class RepositoryTestMustHaveFindAllTestCasesRule implements Rule
{
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

        if ($classReflection->isAbstract() || !str_ends_with($classReflection->getName(), 'RepositoryTest')) {
            return [];
        }

        $errors = [];

        $this->checkForOrderAgnosticism($classReflection, $scope, $errors);

        return $errors;
    }

    private function checkForOrderAgnosticism(ClassReflection $class, Scope $scope, array &$errors): void
    {
        foreach ($class->getNativeReflection()->getMethods() as $method) {
            $methodName = $method->getName();
            if (!str_contains(strtolower($methodName), 'findall')) {
                continue;
            }

            $tracedNode = $scope->getFunctionCallStack()[0] ?? null;
            if (!$tracedNode) {
                continue;
            }

            $nodeFinder = new NodeFinder();
            $dimFetches = $nodeFinder->findInstanceOf($tracedNode, Node\Expr\ArrayDimFetch::class);

            foreach ($dimFetches as $dimFetch) {
                if ($dimFetch->var instanceof Node\Expr\Variable) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        '在测试方法 "%s" 中，可能通过索引访问了 `findAll()` 的返回结果（如 $result[0]）。' . PHP_EOL .
                        '这是一个脆弱的测试，因为 `findAll()` 不保证返回结果的顺序。',
                        $methodName
                    ))->tip(<<<'TIP'
                        避免使用数组索引访问的正确做法：

                        1. 如果需要验证特定数据存在，遍历结果：
                           $found = false;
                           foreach ($results as $item) {
                               if ($item->getName() === 'expected') {
                                   $found = true;
                                   break;
                               }
                           }
                           $this->assertTrue($found);

                        2. 如果需要特定顺序，使用 findBy：
                           $results = $repository->findBy([], ['id' => 'ASC']);
                           // 现在可以安全使用 $results[0]

                        3. 如果只需要一条记录，使用 findOneBy：
                           $entity = $repository->findOneBy(['name' => 'expected']);
                        TIP)->line($dimFetch->getStartLine())->build();
                }
            }
        }
    }
}
