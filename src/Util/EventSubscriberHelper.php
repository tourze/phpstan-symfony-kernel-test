<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Util;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use PHPStan\Broker\ClassNotFoundException;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventSubscriberHelper
{
    public static function isEventSubscriber(string $className, ReflectionProvider $reflectionProvider): bool
    {
        try {
            $classReflection = $reflectionProvider->getClass($className);
        } catch (ClassNotFoundException) {
            // If class is not found, it's not an event subscriber we can check.
            return false;
        }

        // 1. Check if the class implements EventSubscriberInterface
        $eventSubscriberType = new ObjectType(EventSubscriberInterface::class);
        if ($eventSubscriberType->isSuperTypeOf(new ObjectType($className))->yes()) {
            return true;
        }

        // 2. Check for AsEventListener attribute on class or methods
        if (self::hasAttributeRecursive($classReflection, AsEventListener::class)) {
            return true;
        }

        // 3. Check for AsDoctrineListener attribute on class or methods
        if (self::hasAttributeRecursive($classReflection, AsDoctrineListener::class)) {
            return true;
        }

        return false;
    }

    private static function hasAttributeRecursive(
        ClassReflection $classReflection,
        string $attributeName,
    ): bool {
        // Check class attributes
        foreach ($classReflection->getAttributes() as $attribute) {
            if ($attribute->getName() === $attributeName) {
                return true;
            }
        }

        $nativeReflection = $classReflection->getNativeReflection();

        // Check method attributes
        foreach ($nativeReflection->getMethods() as $method) {
            foreach ($method->getAttributes() as $attribute) {
                if ($attribute->getName() === $attributeName) {
                    return true;
                }
            }
        }

        return false;
    }
}
