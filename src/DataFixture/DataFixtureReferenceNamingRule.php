<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\DataFixture;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassConst;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * 检查 DataFixtures 中引用名称的命名规范
 *
 * @implements Rule<ClassConst>
 */
class DataFixtureReferenceNamingRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassConst::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassConst) {
            return [];
        }

        // 检查是否在 DataFixture 类中
        $classReflection = $scope->getClassReflection();
        if (!$classReflection || !$this->isDataFixtureClass($classReflection)) {
            return [];
        }

        $errors = [];

        foreach ($node->consts as $const) {
            $constName = $const->name->name;

            // 检查是否是引用常量（以 _REFERENCE 结尾）
            if (!str_ends_with($constName, '_REFERENCE')) {
                continue;
            }

            // 检查常量名是否符合 UPPER_SNAKE_CASE
            if (!preg_match('/^[A-Z][A-Z0-9_]*_REFERENCE$/', $constName)) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        'DataFixture 引用常量名 "%s" 应该使用 UPPER_SNAKE_CASE 格式（如 USER_REFERENCE, ADMIN_USER_REFERENCE）',
                        $constName
                    )
                )
                    ->line($const->getLine())
                    ->build()
                ;
            }

            // 检查常量值
            if ($const->value instanceof String_) {
                $value = $const->value->value;

                // 检查值是否符合 entity-name-identifier 格式
                if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $value)) {
                    $errors[] = RuleErrorBuilder::message(
                        sprintf(
                            'DataFixture 引用值 "%s" 应该使用 kebab-case 格式（如 "user-reference", "admin-user"）',
                            $value
                        )
                    )
                        ->line($const->getLine())
                        ->tip('引用值应遵循 "entity-name-identifier" 格式，使用小写字母和连字符')
                        ->build()
                    ;
                }

                // 检查常量名和值的一致性
                $expectedValue = $this->convertConstNameToValue($constName);
                if ($value !== $expectedValue && !$this->isReasonableVariation($value, $expectedValue)) {
                    $errors[] = RuleErrorBuilder::message(
                        sprintf(
                            '引用常量 %s 的值 "%s" 与常量名不一致，建议使用 "%s"',
                            $constName,
                            $value,
                            $expectedValue
                        )
                    )
                        ->line($const->getLine())
                        ->tip('常量名和值应保持语义一致性')
                        ->build()
                    ;
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

    /**
     * 将常量名转换为预期的值
     */
    private function convertConstNameToValue(string $constName): string
    {
        // 移除 _REFERENCE 后缀
        $name = str_replace('_REFERENCE', '', $constName);

        // 转换为 kebab-case
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2',
            preg_replace('/([A-Z])([A-Z][a-z])/', '$1-$2',
                preg_replace('/_/', '-', $name)
            )
        ));
    }

    /**
     * 检查值是否是合理的变体
     */
    private function isReasonableVariation(string $actual, string $expected): bool
    {
        // 允许一些常见的变体，如 "admin-user" vs "admin"
        $actualParts = explode('-', $actual);
        $expectedParts = explode('-', $expected);

        // 如果实际值是期望值的前缀或后缀，认为是合理的
        return str_starts_with($actual, $expected)
               || str_starts_with($expected, $actual)
               || (count($actualParts) > 1 && $actualParts[0] === $expectedParts[0]);
    }
}
