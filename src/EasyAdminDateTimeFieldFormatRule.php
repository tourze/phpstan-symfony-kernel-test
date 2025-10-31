<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest;

use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * @implements Rule<MethodCall>
 */
final class EasyAdminDateTimeFieldFormatRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Node\Identifier || 'setFormat' !== $node->name->toString()) {
            return [];
        }

        $callerType = $scope->getType($node->var);
        $easyAdminDateTimeFieldType = new ObjectType(DateTimeField::class);

        if (!$easyAdminDateTimeFieldType->isSuperTypeOf($callerType)->yes()) {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) < 1) {
            return [];
        }

        $formatArg = $args[0]->value;

        if (!$formatArg instanceof String_) {
            return [];
        }

        $formatValue = $formatArg->value;

        // Check for common PHP date() format characters that are invalid in ICU format.
        // 'Y' (4-digit year) and 'i' (minutes) are common mistakes.
        $hasInvalidFormat = preg_match('/(?<!")([Yi])(?! ")/', $formatValue);
        if (false !== $hasInvalidFormat && 0 !== $hasInvalidFormat) {
            return [
                RuleErrorBuilder::message(
                    "EasyAdmin DateTimeField::setFormat() uses ICU format, not PHP date() format. For example, use 'yyyy-MM-dd HH:mm:ss' instead of 'Y-m-d H:i:s'."
                )
                    ->identifier('easyAdmin.invalidDateTimeFieldFormat')
                    ->tip('See ICU formatting guide: https://unicode-org.github.io/icu/userguide/format_parse/datetime/')
                    ->build(),
            ];
        }

        return [];
    }
}
