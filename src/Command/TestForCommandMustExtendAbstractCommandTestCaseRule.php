<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Command;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Symfony\Component\Console\Command\Command;
use Tourze\PHPUnitBase\TestCaseHelper;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * 检查 Command 的测试用例必须继承 AbstractCommandTestCase
 *
 * @implements Rule<InClassNode>
 */
class TestForCommandMustExtendAbstractCommandTestCaseRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return array<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return [];
        }

        // 1. 检查是否是测试类
        if (!TestCaseHelper::isTestClass($classReflection->getName())) {
            return [];
        }

        // 2. 获取CoversClass注解
        $coversClass = TestCaseHelper::extractCoverClass($classReflection->getNativeReflection());
        if (null === $coversClass) {
            return [];
        }

        // 3. 检查被覆盖的类是否是Command类
        if (!$this->isCommandClass($coversClass, $scope)) {
            return [];
        }

        // 4. 检查测试类是否继承了 AbstractCommandTestCase
        $requiredParentClass = AbstractCommandTestCase::class;
        if ($classReflection->isSubclassOf($requiredParentClass)) {
            return [];
        }

        // 5. 如果没有继承，则报错
        return [
            RuleErrorBuilder::message(sprintf(
                '测试类 %s 的测试目标是 Command 类 %s，因此必须继承 %s。',
                $classReflection->getName(),
                $coversClass,
                $requiredParentClass
            ))
                ->identifier('commandTest.mustExtendAbstractCommandTestCase')
                ->tip(sprintf('请将 %s 的父类修改为 %s。', $classReflection->getName(), 'AbstractCommandTestCase'))
                ->build(),
        ];
    }

    private function isCommandClass(string $className, Scope $scope): bool
    {
        try {
            $classType = new ObjectType($className);
            $commandType = new ObjectType(Command::class);

            return $commandType->isSuperTypeOf($classType)->yes();
        } catch (\Throwable) {
            // If unable to resolve type, try to infer from class name
            return str_ends_with($className, 'Command');
        }
    }
}
