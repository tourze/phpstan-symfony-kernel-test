<?php

namespace Tourze\PHPStanSymfonyKernelTest\Util;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Monolog\Attribute\WithMonologChannel;
use PHPStan\Reflection\ClassReflection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\DependencyInjection\Attribute\WhenNot;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Tourze\PHPUnitDoctrineEntity\EntityChecker;
use Tourze\Symfony\AopAsyncBundle\Attribute\Async;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Extension\AbstractExtension;

class ServiceChecker
{
    public const SERVICE_ANNOTATION_NAMES = [
        Autoconfigure::class,
        AsAlias::class,
        AsDecorator::class,
        AutoconfigureTag::class,
        Lazy::class,
        When::class,
        WhenNot::class,
        AsDoctrineListener::class,
        WithMonologChannel::class,
    ];

    public const SERVICE_METHOD_ANNOTATION_NAMES = [
        Async::class,
        AsEventListener::class,
        AsTwigFilter::class,
        AsTwigFunction::class,
        AsTwigComponent::class,
    ];

    public const SERVICE_CLASS_NAMES = [
        AbstractExtension::class,
    ];

    /**
     * 检查一个类是否是一个Symfony服务
     */
    public static function isService(ClassReflection $classReflection): bool
    {
        if (self::isExcludedClass($classReflection)) {
            return false;
        }

        if (RepositoryChecker::isRepositoryClass($classReflection)) {
            return true;
        }

        return self::hasServiceAttributes($classReflection)
            || self::hasServiceMethodAttributes($classReflection)
            || self::hasServiceParentClass($classReflection);
    }

    /**
     * 检查是否为排除的类（实体等）
     */
    private static function isExcludedClass(ClassReflection $classReflection): bool
    {
        return EntityChecker::isEntityClass($classReflection->getNativeReflection());
    }

    /**
     * 检查类是否有服务相关的属性注解
     */
    private static function hasServiceAttributes(ClassReflection $classReflection): bool
    {
        foreach ($classReflection->getAttributes() as $attribute) {
            if (in_array($attribute->getName(), self::SERVICE_ANNOTATION_NAMES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查类的方法是否有服务相关的属性注解
     */
    private static function hasServiceMethodAttributes(ClassReflection $classReflection): bool
    {
        foreach ($classReflection->getNativeReflection()->getMethods() as $method) {
            foreach ($method->getAttributes() as $attribute) {
                if (in_array($attribute->getName(), self::SERVICE_METHOD_ANNOTATION_NAMES, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 检查是否有服务相关的父类
     */
    private static function hasServiceParentClass(ClassReflection $classReflection): bool
    {
        foreach ($classReflection->getParentClassesNames() as $classesName) {
            if (in_array($classesName, self::SERVICE_CLASS_NAMES, true)) {
                return true;
            }
        }

        return false;
    }
}
