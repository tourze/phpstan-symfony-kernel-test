<?php

namespace Tourze\PHPStanSymfonyKernelTest\Architecture;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

final class RepositoryTest
{
    public const SRC_REPO_REGEX = '@-bundle/src/Repository/@';

    /**
     * 命名空间有 Tests\Repository\，并且不是抽象类，必须继承 AbstractRepositoryTestCase
     */
    #[TestRule]
    public function repository_tests_must_extends_AbstractRepositoryTestCase(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::AllOf(
                Selector::classname('@\\\Tests\\\Repository\\\@', true),
                Selector::NOT(
                    Selector::isAbstract(),
                ),
            ))
            ->shouldExtend()
            ->classes(
                Selector::classname(AbstractRepositoryTestCase::class),
            )
        ;
    }

    /**
     * 检查所有 Repository 类必须继承自 ServiceEntityRepository
     */
    #[TestRule]
    public function repository_must_extends_ServiceEntityRepository(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::withFilepath(self::SRC_REPO_REGEX, true))
            ->shouldExtend()
            ->classes(Selector::classname(ServiceEntityRepository::class))
            ->because(
                '仓库类（Repository）必须继承自 ' . ServiceEntityRepository::class,
                "正确写法示例：\n" .
                "```php\n" .
                "class %s extends ServiceEntityRepository\n" .
                "{\n" .
                "    public function __construct(ManagerRegistry \$registry)\n" .
                "    {\n" .
                "        parent::__construct(\$registry, YourEntity::class);\n" .
                "    }\n" .
                "}\n" .
                '```',
            )
        ;
    }

    /**
     * 禁止抽象类继承 ServiceEntityRepository
     *
     * ServiceEntityRepository 是为具体实体仓库设计的，应该直接被最终的仓库类继承。
     * 创建抽象的中间层仓库类违反了 Doctrine 的设计原则，会导致：
     * - 服务容器自动装配问题
     * - 实体类型推断失效
     * - 增加不必要的继承层次
     */
    #[TestRule]
    public function abstract_repository_forbid_extends_ServiceEntityRepository(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::AllOf(
                Selector::withFilepath(self::SRC_REPO_REGEX, true),
                Selector::isAbstract(),
                Selector::extends(ServiceEntityRepository::class),
            ))
            ->shouldNotExist()
            ->because('创建抽象的中间层仓库类违反了 Doctrine 的设计原则')
        ;
    }
}
