<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Command;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/**
 * 检查命令名称必须使用冒号分隔格式（如 app:create-user）
 *
 * @implements Rule<InClassNode>
 */
class CommandNameFormatRule implements Rule
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
        $classReflection = $node->getClassReflection();

        // 检查是否是 Command 的子类
        if (!$this->isCommandSubclass($classReflection)) {
            return [];
        }

        // 检查是否是抽象类
        if ($classReflection->isAbstract()) {
            return [];
        }

        $nativeReflection = $classReflection->getNativeReflection();
        $attributes = $nativeReflection->getAttributes(AsCommand::class);

        if (0 === count($attributes)) {
            // 这个会被 RequireAsCommandAttributeRule 处理
            return [];
        }

        $attribute = $attributes[0];
        $arguments = $attribute->getArguments();

        // 获取命令名称
        $commandName = null;
        if (isset($arguments['name'])) {
            $commandName = $arguments['name'];
        } elseif (isset($arguments[0])) {
            $commandName = $arguments[0];
        }

        if (null === $commandName) {
            // 这个会被其他规则处理
            return [];
        }

        // 如果命令名称是从常量获取的，尝试解析
        if ($nativeReflection->hasConstant('NAME')) {
            $nameConstant = $nativeReflection->getConstant('NAME');
            if (is_string($nameConstant)) {
                $commandName = $nameConstant;
            }
        }

        // 检查命令名称格式
        if (!$this->isValidCommandNameFormat($commandName)) {
            return [
                RuleErrorBuilder::message(sprintf(
                    '命令名称 "%s"（类 %s）必须使用冒号分隔格式（例如："app:create-user"）。',
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

    private function isValidCommandNameFormat(string $commandName): bool
    {
        // 命令名称应该包含至少一个冒号
        if (!str_contains($commandName, ':')) {
            return false;
        }

        // 命令名称不应该以冒号开头或结尾
        if (str_starts_with($commandName, ':') || str_ends_with($commandName, ':')) {
            return false;
        }

        // 命令名称应该符合格式：namespace:action 或 namespace:sub:action
        // 允许的字符：小写字母、数字、连字符、冒号
        if (!preg_match('/^[a-z0-9]+(?:[:-][a-z0-9]+)*:[a-z0-9]+(?:-[a-z0-9]+)*$/', $commandName)) {
            return false;
        }

        return true;
    }
}
