<?php

namespace Tourze\PHPStanSymfonyKernelTest\Util;

class BundleChecker
{
    /**
     * 判断类是否属于 Bundle（以 -bundle 结尾的包）
     */
    public static function isBundleClass(string $className): bool
    {
        // 检查类名的命名空间是否包含以 "Bundle" 结尾的部分
        $parts = explode('\\', $className);

        foreach ($parts as $part) {
            if (str_ends_with($part, 'Bundle')) {
                return true;
            }
        }

        // 对于 packages 下的包，检查包名是否以 -bundle 结尾
        if (str_starts_with($className, 'Tourze\\')) {
            // 从命名空间中提取包名
            // 例如: Tourze\BacktraceHelper\Backtrace -> BacktraceHelper
            // 例如: Tourze\SymfonyCacheHotkeyBundle\Service\HotkeySmartCache -> SymfonyCacheHotkeyBundle
            $namespaceParts = explode('\\', $className);
            if (isset($namespaceParts[1])) {
                $packageName = $namespaceParts[1];
                // 将驼峰命名转换为kebab-case，然后检查是否以-bundle结尾
                $kebabCase = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $packageName));

                return str_ends_with($kebabCase, '-bundle');
            }
        }

        return false;
    }

    public static function inSymfonyBundle(string $className): bool
    {
        $reflectionClass = new \ReflectionClass($className);

        return str_contains($reflectionClass->getFileName(), '-bundle/');
    }
}
