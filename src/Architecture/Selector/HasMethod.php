<?php

namespace Tourze\PHPStanSymfonyKernelTest\Architecture\Selector;

use PHPat\Selector\SelectorInterface;
use PHPStan\Reflection\ClassReflection;

/**
 * 检查类是否有某个方法
 */
final class HasMethod implements SelectorInterface
{
    public function __construct(
        private readonly string $methodName,
        private readonly ?bool $isPublic = null,
    ) {
    }

    public function matches(ClassReflection $classReflection): bool
    {
        if (!$classReflection->hasMethod($this->methodName)) {
            return false;
        }

        // 检查是否是public
        if (null !== $this->isPublic && $classReflection->getNativeReflection()->getMethod($this->methodName)->isPublic() !== $this->isPublic) {
            return false;
        }

        return true;
    }

    public function getName(): string
    {
        return '- has method ' . $this->methodName;
    }
}
