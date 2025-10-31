<?php

namespace Tourze\PHPStanSymfonyKernelTest\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPStanSymfonyKernelTest\NoLazyReadonlyAutoconfigureRule;

/**
 * @extends RuleTestCase<NoLazyReadonlyAutoconfigureRule>
 * @internal
 */
#[CoversClass(NoLazyReadonlyAutoconfigureRule::class)]
class NoLazyReadonlyAutoconfigureRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoLazyReadonlyAutoconfigureRule();
    }

    public function testReadonlyClassWithLazyAutoconfigureReportsError(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/ReadonlyClassWithLazyAutoconfigure.php'], [
            [
                '[BUG] Readonly class with #[Autoconfigure] cannot be lazy.',
                5,
            ],
        ]);

        // 验证规则实例存在
        $this->assertInstanceOf(NoLazyReadonlyAutoconfigureRule::class, $this->getRule());
    }

    public function testReadonlyClassWithoutLazyAutoconfigureIsValid(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/ReadonlyClassWithoutLazyAutoconfigure.php'], []);
        $this->assertInstanceOf(NoLazyReadonlyAutoconfigureRule::class, $this->getRule());
    }

    public function testNonReadonlyClassWithLazyAutoconfigureIsValid(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/NonReadonlyClassWithLazyAutoconfigure.php'], []);
        $this->assertInstanceOf(NoLazyReadonlyAutoconfigureRule::class, $this->getRule());
    }

    public function testReadonlyClassWithoutAutoconfigureIsValid(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/ReadonlyClassWithoutAutoconfigure.php'], []);
        $this->assertInstanceOf(NoLazyReadonlyAutoconfigureRule::class, $this->getRule());
    }

    public function testReadonlyClassWithLazyFalseAutoconfigureIsValid(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/ReadonlyClassWithLazyFalseAutoconfigure.php'], []);
        $this->assertInstanceOf(NoLazyReadonlyAutoconfigureRule::class, $this->getRule());
    }

    public function testProcessNodeDirectly(): void
    {
        $rule = $this->getRule();
        $this->assertSame('PhpParser\Node\Stmt\Class_', $rule->getNodeType());
    }
}
