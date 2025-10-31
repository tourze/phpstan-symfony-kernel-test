<?php

namespace Tourze\PHPStanSymfonyKernelTest\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

final class CommandTest
{
    /**
     * 命名空间有 Tests\Command\，并且不是抽象类，必须继承 AbstractIntegrationTestCase
     */
    #[TestRule]
    public function command_tests_must_extends_AbstractIntegrationTestCase(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::AllOf(
                Selector::classname('@\\\Tests\\\Command\\\@', true),
                Selector::NOT(
                    Selector::isAbstract(),
                ),
            ))
            ->shouldExtend()
            ->classes(
                Selector::classname(AbstractIntegrationTestCase::class),
            )
            ->because('继承AbstractIntegrationTestCase才能使用服务容器')
        ;
    }
}
