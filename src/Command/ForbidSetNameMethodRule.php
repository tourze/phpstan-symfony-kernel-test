<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Command;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Console\Command\Command;

/**
 * 禁止在构造函数或 configure 方法中使用 setName() 方法（应该使用属性定义）
 *
 * @implements Rule<MethodCall>
 */
class ForbidSetNameMethodRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // 检查是否调用了 setName 方法
        if (!$node->name instanceof Identifier || 'setName' !== $node->name->name) {
            return [];
        }

        // 检查是否在类方法中
        if (!$scope->isInClass()) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return [];
        }

        // 检查是否是 Command 的子类
        if (!$classReflection->isSubclassOf(Command::class)) {
            return [];
        }

        // 检查是否在构造函数或 configure 方法中
        $functionName = $scope->getFunctionName();
        if (null === $functionName) {
            return [];
        }

        if ('__construct' === $functionName || 'configure' === $functionName) {
            // 检查调用对象是否是 $this
            if ($node->var instanceof Node\Expr\Variable && 'this' === $node->var->name) {
                return [
                    RuleErrorBuilder::message(sprintf(
                        '禁止在 %s 方法中使用 setName()。请使用 #[AsCommand] 属性来定义命令名称。',
                        $functionName
                    ))
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }

            // 检查 parent::setName()
            if ($node->var instanceof Node\Expr\StaticCall
                && $node->var->class instanceof Node\Name
                && in_array($node->var->class->toString(), ['parent', 'self', 'static'], true)) {
                return [
                    RuleErrorBuilder::message(sprintf(
                        '禁止在 %s 方法中使用 %s::setName()。请使用 #[AsCommand] 属性来定义命令名称。',
                        $functionName,
                        $node->var->class->toString()
                    ))
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }
        }

        return [];
    }
}
