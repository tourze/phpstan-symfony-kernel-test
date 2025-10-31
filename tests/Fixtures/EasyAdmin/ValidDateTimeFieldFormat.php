<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Tests\Fixtures\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;

final class ValidDateTimeFieldFormat
{
    public function configure(): void
    {
        DateTimeField::new('createdAt')->setFormat('yyyy_MM_dd HH:mm:ss');
        DateTimeField::new('createdAt')->setFormat('yyyy-MM-dd HH:mm:ss');
        DateTimeField::new('createdAt')->setFormat("yyyy-MM-dd'T'HH:mm:ss");
    }
}
