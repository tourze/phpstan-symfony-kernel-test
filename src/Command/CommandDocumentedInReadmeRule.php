<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Command;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/**
 * 检查Command命令是否在包的README.md或README.zh-CN.md中有记录
 *
 * @implements Rule<InClassNode>
 */
class CommandDocumentedInReadmeRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        // 检查是否是 Command 的子类
        if (!$this->isCommandSubclass($classReflection)) {
            return [];
        }

        // 检查是否是抽象类
        if ($classReflection->isAbstract()) {
            return [];
        }

        // 获取命令名称
        $commandName = $this->getCommandName($classReflection);
        if (null === $commandName) {
            // 如果没有命令名称，其他规则会处理
            return [];
        }

        // 获取包路径
        $packagePath = $this->getPackagePath($scope->getFile());
        if (null === $packagePath) {
            // 如果不在包目录中，跳过检查
            return [];
        }

        // 检查README文件
        $readmeFiles = [
            $packagePath . '/README.md',
            $packagePath . '/README.zh-CN.md',
        ];

        $foundInReadme = false;
        foreach ($readmeFiles as $readmeFile) {
            if (file_exists($readmeFile)) {
                $content = file_get_contents($readmeFile);
                if (false !== $content && $this->isCommandDocumentedInContent($commandName, $content)) {
                    $foundInReadme = true;
                    break;
                }
            }
        }

        if (!$foundInReadme) {
            return [
                RuleErrorBuilder::message(sprintf(
                    '命令 "%s"（类 %s）未在包的 README.md 或 README.zh-CN.md 文件中记录。',
                    $commandName,
                    $classReflection->getName()
                ))
                    ->line($node->getOriginalNode()->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    private function isCommandSubclass(ClassReflection $classReflection): bool
    {
        if (Command::class === $classReflection->getName()) {
            return false;
        }

        return $classReflection->isSubclassOf(Command::class);
    }

    private function getCommandName(ClassReflection $classReflection): ?string
    {
        $nativeReflection = $classReflection->getNativeReflection();

        // 首先尝试从 AsCommand 属性获取
        $attributes = $nativeReflection->getAttributes(AsCommand::class);
        if (count($attributes) > 0) {
            $attribute = $attributes[0];
            $arguments = $attribute->getArguments();

            if (isset($arguments['name'])) {
                return $arguments['name'];
            }
            if (isset($arguments[0])) {
                return $arguments[0];
            }
        }

        // 如果没有属性，尝试从常量获取
        if ($nativeReflection->hasConstant('NAME')) {
            $nameConstant = $nativeReflection->getConstant('NAME');
            if (is_string($nameConstant)) {
                return $nameConstant;
            }
        }

        return null;
    }

    private function getPackagePath(string $filePath): ?string
    {
        // 检查是否在 packages 目录中
        if (!str_contains($filePath, '/packages/')) {
            return null;
        }

        // 提取包路径
        if (preg_match('#(.*/packages/[^/]+)/#', $filePath, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function isCommandDocumentedInContent(string $commandName, string $content): bool
    {
        // 检查多种可能的文档格式
        $patterns = [
            // 直接提到命令名称
            preg_quote($commandName, '/'),
            // 在代码块中
            '`' . preg_quote($commandName, '/') . '`',
            // 在命令示例中
            'bin/console\s+' . preg_quote($commandName, '/'),
            'php\s+bin/console\s+' . preg_quote($commandName, '/'),
            // 在表格中
            '\|\s*' . preg_quote($commandName, '/') . '\s*\|',
            // 作为标题
            '#+\s+.*' . preg_quote($commandName, '/'),
        ];

        foreach ($patterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $content)) {
                return true;
            }
        }

        return false;
    }
}
