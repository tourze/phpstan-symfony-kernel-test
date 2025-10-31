<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClassLike>
 */
class NoTestSupportFilesRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassLike::class;
    }

    /**
     * @param ClassLike $node
     * @param Scope $scope
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $filePath = $scope->getFile();
        $normalizedPath = str_replace('\\', '/', $filePath);

        if (str_contains($normalizedPath, '/tests/Support/')) {
            return [
                RuleErrorBuilder::message('Test support files are not allowed. Please use mocks or stubs directly in the test file.')
                    ->identifier('noTestSupportFiles')
                    ->build(),
            ];
        }

        return [];
    }
}
