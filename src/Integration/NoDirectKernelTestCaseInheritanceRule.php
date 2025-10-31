<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Integration;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHPStan 规则：禁止在 packages 目录下直接继承 KernelTestCase
 *
 * 此规则确保：
 * 1. 除了 symfony-testing-framework 包之外，packages 目录下的测试类不能直接继承 KernelTestCase
 * 2. 建议使用 AbstractIntegrationTestCase 替代
 *
 * @implements Rule<Class_>
 */
class NoDirectKernelTestCaseInheritanceRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        assert($node instanceof Class_);

        // 获取文件路径
        $fileName = $scope->getFile();

        // 检查是否在 packages 目录下
        if (!$this->isInPackagesDirectory($fileName)) {
            return [];
        }

        // 如果是 symfony-testing-framework 包，则允许继承 KernelTestCase
        if ($this->isInSymfonyIntegrationTestKernelPackage($fileName)) {
            return [];
        }

        // 检查是否直接继承 KernelTestCase
        if (null === $node->extends) {
            return [];
        }

        $extendedClass = $node->extends;
        $extendedClassName = $extendedClass->toString();

        // 检查是否直接继承 KernelTestCase
        if ('KernelTestCase' === $extendedClassName
            || 'Symfony\Bundle\FrameworkBundle\Test\KernelTestCase' === $extendedClassName) {
            $className = $node->name?->toString() ?? 'unknown';

            return [
                RuleErrorBuilder::message(
                    sprintf(
                        '类 "%s" 不能直接继承 KernelTestCase。请使用 symfony-testing-framework 包中的 AbstractIntegrationTestCase。',
                        $className
                    )
                )
                    ->tip('将 "extends KernelTestCase" 修改为 "extends AbstractIntegrationTestCase" 并添加 "use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;"')
                    ->build(),
            ];
        }

        // 检查是否继承了自定义的 IntegrationTestCase 基类
        if ($this->isCustomIntegrationTestCase($extendedClassName)) {
            $className = $node->name?->toString() ?? 'unknown';

            return [
                RuleErrorBuilder::message(
                    sprintf(
                        '类 "%s" 继承了自定义的 IntegrationTestCase。请使用 symfony-testing-framework 包中的 AbstractIntegrationTestCase。',
                        $className
                    )
                )
                    ->tip('用 AbstractIntegrationTestCase 替换自定义的 IntegrationTestCase 并实现 configureBundles() 方法')
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * 检查文件是否在 packages 目录下
     */
    private function isInPackagesDirectory(string $fileName): bool
    {
        return str_contains($fileName, '/packages/');
    }

    /**
     * 检查是否在 symfony-testing-framework 包内
     */
    private function isInSymfonyIntegrationTestKernelPackage(string $fileName): bool
    {
        return str_contains($fileName, '/packages/symfony-testing-framework/');
    }

    /**
     * 检查是否是自定义的 IntegrationTestCase 类
     */
    private function isCustomIntegrationTestCase(string $className): bool
    {
        // 匹配常见的自定义 IntegrationTestCase 命名模式
        return str_contains($className, 'IntegrationTestCase')
               && !str_contains($className, 'AbstractIntegrationTestCase');
    }
}
