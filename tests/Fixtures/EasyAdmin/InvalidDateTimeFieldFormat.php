<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Tests\Fixtures\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;

final class InvalidDateTimeFieldFormat
{
    public function configure(): void
    {
        DateTimeField::new('createdAt')->setFormat('Y-m-d H:i:s');
    }
}
