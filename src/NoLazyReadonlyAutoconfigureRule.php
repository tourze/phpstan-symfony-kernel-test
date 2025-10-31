<?php

namespace Tourze\PHPStanSymfonyKernelTest;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * @implements Rule<Class_>
 */
class NoLazyReadonlyAutoconfigureRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_ || !$node->isReadonly()) {
            return [];
        }

        return $this->checkAutoconfigureAttributes($node);
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function checkAutoconfigureAttributes(Class_ $node): array
    {
        foreach ($node->attrGroups as $attrGroup) {
            $error = $this->checkAttributeGroup($attrGroup);
            if (null !== $error) {
                return [$error];
            }
        }

        return [];
    }

    private function checkAttributeGroup(AttributeGroup $attrGroup): ?IdentifierRuleError
    {
        foreach ($attrGroup->attrs as $attr) {
            if ($this->isAutoconfigureAttribute($attr)) {
                $error = $this->checkLazyAttribute($attr);
                if (null !== $error) {
                    return $error;
                }
            }
        }

        return null;
    }

    private function isAutoconfigureAttribute(Attribute $attr): bool
    {
        return Autoconfigure::class === $attr->name->toString();
    }

    private function checkLazyAttribute(Attribute $attr): ?IdentifierRuleError
    {
        foreach ($attr->args as $arg) {
            if ($this->isLazyArgument($arg) && $this->isArgumentTrue($arg)) {
                return RuleErrorBuilder::message('[BUG] Readonly class with #[Autoconfigure] cannot be lazy.')
                    ->identifier('noLazyReadonlyAutoconfigure')
                    ->build()
                ;
            }
        }

        return null;
    }

    private function isLazyArgument(Arg $arg): bool
    {
        return $arg->name instanceof Identifier && 'lazy' === $arg->name->toString();
    }

    private function isArgumentTrue(Arg $arg): bool
    {
        return $arg->value instanceof ConstFetch
            && 'true' === strtolower($arg->value->name->toString());
    }
}
