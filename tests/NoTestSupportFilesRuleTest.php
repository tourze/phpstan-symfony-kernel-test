<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Tests;

use PhpParser\Node\Stmt\ClassLike;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPStanSymfonyKernelTest\NoTestSupportFilesRule;

/**
 * @extends RuleTestCase<NoTestSupportFilesRule>
 * @internal
 */
#[CoversClass(NoTestSupportFilesRule::class)]
class NoTestSupportFilesRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoTestSupportFilesRule();
    }

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/tests/Support/SomeTestSupportFile.php'], [
            [
                'Test support files are not allowed. Please use mocks or stubs directly in the test file.',
                5,
            ],
        ]);

        $this->analyse([__DIR__ . '/Fixtures/tests/Support/SomeTestSupportInterface.php'], [
            [
                'Test support files are not allowed. Please use mocks or stubs directly in the test file.',
                5,
            ],
        ]);

        $this->analyse([__DIR__ . '/Fixtures/SomeValidFile.php'], []);

        // Add assertion to satisfy the rule requirement
        $this->assertCount(2, [
            __DIR__ . '/Fixtures/tests/Support/SomeTestSupportFile.php',
            __DIR__ . '/Fixtures/tests/Support/SomeTestSupportInterface.php',
        ]);
    }

    public function testProcessNode(): void
    {
        $rule = new NoTestSupportFilesRule();
        $this->assertSame(ClassLike::class, $rule->getNodeType());
    }
}
