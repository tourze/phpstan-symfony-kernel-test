<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\DataFixture;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * 检查 DataFixtures 中的图片 URL 是否使用了无效的占位符域名
 *
 * @implements Rule<String_>
 */
class DataFixtureImageUrlRule implements Rule
{
    /**
     * 无效的域名列表
     */
    private const INVALID_DOMAINS = [
        'example.com',
        'test.com',
        'placeholder.com',
        'placekitten.com',
        'placehold.it',
        'lorempixel.com',
        'loremflickr.com',
        'dummyimage.com',
        'via.placeholder.com',
        'picsum.photos',
        'fakeimg.pl',
    ];

    /**
     * 推荐的图片服务
     */
    private const RECOMMENDED_SERVICES = [
        'unsplash.com',
        'images.unsplash.com',
        'source.unsplash.com',
        'pexels.com',
        'pixabay.com',
    ];

    public function getNodeType(): string
    {
        return String_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof String_) {
            return [];
        }

        // 检查是否在 DataFixture 类中
        $classReflection = $scope->getClassReflection();
        if (!$classReflection || !$this->isDataFixtureClass($classReflection)) {
            return [];
        }

        $value = $node->value;

        // 检查是否是 URL
        if (!$this->isUrl($value)) {
            return [];
        }

        // 检查是否可能是图片 URL
        if (!$this->isPossibleImageUrl($value)) {
            return [];
        }

        $errors = [];

        // 检查是否使用了无效域名
        $domain = $this->extractDomain($value);
        if ($domain && $this->isInvalidDomain($domain)) {
            $errors[] = RuleErrorBuilder::message(
                sprintf(
                    'DataFixtures 中不应使用无效的占位符图片服务 "%s"',
                    $domain
                )
            )
                ->line($node->getStartLine())
                ->tip(sprintf(
                    '请使用真实的图片服务，推荐使用：%s。例如：https://images.unsplash.com/photo-xxx',
                    implode(', ', self::RECOMMENDED_SERVICES)
                ))
                ->build()
            ;
        }

        // 检查是否在设置图片相关属性
        $parent = $node->getAttribute('parent');
        if ($parent instanceof Node\Arg
            && $parent->getAttribute('parent') instanceof MethodCall) {
            $methodCall = $parent->getAttribute('parent');
            if ($methodCall->name instanceof Node\Identifier) {
                $methodName = $methodCall->name->name;

                // 检查是否是设置图片相关的方法
                if ($this->isImageSetterMethod($methodName) && !$this->isValidImageService($domain)) {
                    $errors[] = RuleErrorBuilder::message(
                        sprintf(
                            '方法 %s() 中使用的图片 URL 应该来自推荐的图片服务',
                            $methodName
                        )
                    )
                        ->line($node->getStartLine())
                        ->tip('推荐使用 Unsplash 等真实图片服务，以提供更好的测试数据质量')
                        ->build()
                    ;
                }
            }
        }

        return $errors;
    }

    /**
     * 检查是否为 DataFixture 类
     */
    private function isDataFixtureClass(ClassReflection $classReflection): bool
    {
        // 检查是否继承了 Doctrine\Bundle\FixturesBundle\Fixture
        if ($classReflection->isSubclassOf('Doctrine\Bundle\FixturesBundle\Fixture')) {
            return true;
        }

        // 检查命名空间是否包含 DataFixtures
        return str_contains($classReflection->getName(), 'DataFixtures');
    }

    /**
     * 检查是否是email地址
     */
    private function isEmailAddress(string $value): bool
    {
        return str_contains($value, '@') && false !== filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    /**
     * 检查是否是 URL
     */
    private function isUrl(string $value): bool
    {
        // 排除email地址
        if ($this->isEmailAddress($value)) {
            return false;
        }

        return str_starts_with($value, 'http://')
               || str_starts_with($value, 'https://')
               || (str_contains($value, '.com') && !str_contains($value, '@'))
               || (str_contains($value, '.org') && !str_contains($value, '@'))
               || (str_contains($value, '.net') && !str_contains($value, '@'));
    }

    /**
     * 检查是否可能是图片 URL
     */
    private function isPossibleImageUrl(string $value): bool
    {
        // 检查常见的图片扩展名
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        foreach ($imageExtensions as $ext) {
            if (str_contains(strtolower($value), '.' . $ext)) {
                return true;
            }
        }

        // 检查是否包含图片相关的路径
        $imagePaths = ['/image', '/img', '/photo', '/picture', '/avatar', '/banner', '/logo', '/icon'];
        foreach ($imagePaths as $path) {
            if (str_contains(strtolower($value), $path)) {
                return true;
            }
        }

        // 检查是否包含已知的图片服务域名
        foreach (array_merge(self::INVALID_DOMAINS, self::RECOMMENDED_SERVICES) as $domain) {
            if (str_contains($value, $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 提取域名
     */
    private function extractDomain(string $url): ?string
    {
        $parts = parse_url($url);
        if (isset($parts['host'])) {
            return $parts['host'];
        }

        // 尝试从路径中提取域名
        if (preg_match('/([a-zA-Z0-9.-]+\.(com|org|net|io|co|dev))/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * 检查是否是无效域名
     */
    private function isInvalidDomain(string $domain): bool
    {
        foreach (self::INVALID_DOMAINS as $invalidDomain) {
            if ($domain === $invalidDomain || str_ends_with($domain, '.' . $invalidDomain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查是否是设置图片的方法
     */
    private function isImageSetterMethod(string $methodName): bool
    {
        $imageSetters = [
            'setImage',
            'setAvatar',
            'setPhoto',
            'setPicture',
            'setThumbnail',
            'setBanner',
            'setLogo',
            'setIcon',
            'setCover',
            'setImageUrl',
            'setAvatarUrl',
            'setPhotoUrl',
            'setPictureUrl',
        ];

        return in_array($methodName, $imageSetters, true)
               || str_contains(strtolower($methodName), 'image')
               || str_contains(strtolower($methodName), 'photo')
               || str_contains(strtolower($methodName), 'picture')
               || str_contains(strtolower($methodName), 'avatar');
    }

    /**
     * 检查是否是有效的图片服务
     */
    private function isValidImageService(string $domain): bool
    {
        foreach (self::RECOMMENDED_SERVICES as $validDomain) {
            if ($domain === $validDomain || str_ends_with($domain, '.' . $validDomain)) {
                return true;
            }
        }

        return false;
    }
}
