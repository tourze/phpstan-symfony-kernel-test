<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPStanSymfonyKernelTest\EasyAdminDateTimeFieldFormatRule;

/**
 * @extends RuleTestCase<EasyAdminDateTimeFieldFormatRule>
 * @internal
 */
#[CoversClass(EasyAdminDateTimeFieldFormatRule::class)]
final class EasyAdminDateTimeFieldFormatRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new EasyAdminDateTimeFieldFormatRule();
    }

    public function testDetectsPhpDateTokens(): void
    {
        $result = $this->analyse(
            [__DIR__ . '/Fixtures/EasyAdmin/InvalidDateTimeFieldFormat.php'],
            [
                [
                    "EasyAdmin DateTimeField::setFormat() uses ICU format, not PHP date() format. For example, use 'yyyy-MM-dd HH:mm:ss' instead of 'Y-m-d H:i:s'.\n    💡 See ICU formatting guide: https://unicode-org.github.io/icu/userguide/format_parse/datetime/",
                    13,
                ],
            ]
        );

        // 确保分析完成并返回预期结果
        $this->assertNull($result);
    }

    public function testAllowsIcuFormats(): void
    {
        $result = $this->analyse(
            [__DIR__ . '/Fixtures/EasyAdmin/ValidDateTimeFieldFormat.php'],
            []
        );

        // 确保分析完成且没有错误
        $this->assertNull($result);
    }
}
