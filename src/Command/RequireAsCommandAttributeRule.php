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
 * 检查所有继承自 Symfony\Component\Console\Command\Command 的类必须使用 #[AsCommand] 属性
 *
 * @implements Rule<InClassNode>
 */
class RequireAsCommandAttributeRule implements Rule
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

        // 检查是否有 AsCommand 属性
        $nativeReflection = $classReflection->getNativeReflection();
        $attributes = $nativeReflection->getAttributes(AsCommand::class);

        if (0 === count($attributes)) {
            return [
                RuleErrorBuilder::message(sprintf(
                    '命令类 %s 必须使用 #[AsCommand] 属性来定义命令。',
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
