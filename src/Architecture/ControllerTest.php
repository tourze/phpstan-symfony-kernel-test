<?php

namespace Tourze\PHPStanSymfonyKernelTest\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

final class ControllerTest
{
    #[TestRule]
    public function crud_controller_test_must_extends_AbstractEasyAdminControllerTestCase(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::AllOf(
                Selector::classname('@\\\Tests\\\(.*?)CrudControllerTest@', true),
                Selector::NOT(
                    Selector::isAbstract(),
                ),
            ))
            ->shouldExtend()
            ->classes(
                Selector::classname(AbstractEasyAdminControllerTestCase::class),
            )
            ->because('改为继承 AbstractEasyAdminControllerTestCase 以统一测试方法，必要时请你重构这个测试用例（太多错误的话）')
        ;
    }
}
