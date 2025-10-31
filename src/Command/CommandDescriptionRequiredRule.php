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
 * 检查 #[AsCommand] 属性必须提供 description 参数
 *
 * @implements Rule<InClassNode>
 */
class CommandDescriptionRequiredRule implements Rule
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

        // 检查是否有 description 参数
        if (!isset($arguments['description']) && !isset($arguments[1])) {
            return [
                RuleErrorBuilder::message(sprintf(
                    '命令类 %s 必须在 #[AsCommand] 属性中提供 description 参数。',
                    $classReflection->getName()
                ))
                    ->line($node->getOriginalNode()->getStartLine())
                    ->build(),
            ];
        }

        // 获取 description 值
        $description = null;
        if (isset($arguments['description'])) {
            $description = $arguments['description'];
        } elseif (isset($arguments[1])) {
            $description = $arguments[1];
        }

        // 检查 description 是否为空或者只包含空白字符
        if (null !== $description && is_string($description) && '' === trim($description)) {
            return [
                RuleErrorBuilder::message(sprintf(
                    '命令类 %s 必须在 #[AsCommand] 属性中提供非空的 description 参数。',
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
}
