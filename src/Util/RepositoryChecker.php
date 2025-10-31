<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Util;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPStan\Reflection\ClassReflection;

class RepositoryChecker
{
    /**
     * 检查类是否为 Repository 类
     */
    public static function isRepositoryClass(ClassReflection $classReflection): bool
    {
        // 检查类名是否以 Repository 结尾
        if (!str_ends_with($classReflection->getName(), 'Repository')) {
            return false;
        }

        // 检查是否在 Repository 命名空间中
        if (!str_contains($classReflection->getName(), '\Repository\\')) {
            return false;
        }

        // 检查是否继承自 ServiceEntityRepository 或其他 Repository 基类
        $nativeReflection = $classReflection->getNativeReflection();
        $parentClass = $nativeReflection->getParentClass();

        while ($parentClass) {
            $parentName = $parentClass->getName();

            // 检查是否继承自 ServiceEntityRepository
            if (ServiceEntityRepository::class === $parentName) {
                return true;
            }

            // 检查是否继承自其他 Repository 相关基类
            if (str_contains($parentName, 'EntityRepository')
                || str_contains($parentName, 'Repository')) {
                return true;
            }

            $parentClass = $parentClass->getParentClass();
        }

        return false;
    }
}
