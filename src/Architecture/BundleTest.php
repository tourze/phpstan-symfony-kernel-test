<?php

namespace Tourze\PHPStanSymfonyKernelTest\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

final class BundleTest
{
    #[TestRule]
    public function bundle_test_must_extends_AbstractBundleTestCase(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::AllOf(
                Selector::classname('@\\\Tests\\\(.*)BundleTest@', true),
                Selector::NOT(
                    Selector::isAbstract(),
                ),
            ))
            ->shouldExtend()
            ->classes(
                Selector::classname(AbstractBundleTestCase::class),
            )
            ->because('改用AbstractBundleTestCase以统一测试方法，必要时请你重构这个测试用例（太多错误的话）')
        ;
    }
}
