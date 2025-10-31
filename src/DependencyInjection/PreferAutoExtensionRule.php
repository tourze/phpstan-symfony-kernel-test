<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\DependencyInjection;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

/**
 * If a class extends Symfony\Component\DependencyInjection\Extension\Extension and is not abstract,
 * it must extend \Tourze\SymfonyDependencyServiceLoader\AutoExtension to reduce boilerplate code.
 *
 * @implements Rule<InClassNode>
 */
class PreferAutoExtensionRule implements Rule
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

        // We are interested in children of Extension
        if (!$classReflection->isSubclassOf(Extension::class)) {
            return [];
        }

        // It should not be abstract
        if ($classReflection->isAbstract()) {
            return [];
        }

        // It should not be AutoExtension itself, or a child of it
        if (AutoExtension::class === $classReflection->getName() || $classReflection->isSubclassOf(AutoExtension::class)) {
            return [];
        }

        // Check the parent class
        $parentClass = $classReflection->getParentClass();
        if (!$parentClass) {
            return [];
        }

        // If the parent is Extension, then it's a direct descendant that is not abstract.
        // This is the case we want to flag.
        if (Extension::class === $parentClass->getName()) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Extension 类 %s 应继承 %s 而不是 %s，以减少模板代码。',
                    $classReflection->getName(),
                    AutoExtension::class,
                    Extension::class,
                ))
                    ->tip('请将基类更改为 \Tourze\SymfonyDependencyServiceLoader\AutoExtension。')
                    ->build(),
            ];
        }

        return [];
    }
}
