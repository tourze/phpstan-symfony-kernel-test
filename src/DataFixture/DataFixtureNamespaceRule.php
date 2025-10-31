<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\DataFixture;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * 检查 DataFixtures 的命名空间和文件命名规范
 *
 * @implements Rule<InClassNode>
 */
class DataFixtureNamespaceRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        // 只检查继承了 Fixture 的类
        if (!$classReflection->isSubclassOf('Doctrine\Bundle\FixturesBundle\Fixture')) {
            return [];
        }

        $errors = [];
        $className = $classReflection->getName();
        $fileName = $classReflection->getFileName();

        // 从完整类名中提取短名称
        $parts = explode('\\', $className);
        $shortName = end($parts);

        // 检查命名空间是否包含 DataFixtures
        if (!str_contains($className, '\DataFixtures\\')) {
            $errors[] = RuleErrorBuilder::message(
                sprintf(
                    'DataFixture 类 "%s" 应该位于 DataFixtures 命名空间中',
                    $shortName
                )
            )
                ->line($node->getOriginalNode()->getStartLine())
                ->tip('将类移动到正确的命名空间，例如：App\DataFixtures\ 或 YourBundle\DataFixtures\\')
                ->build()
            ;
        }

        // 检查类名是否符合命名规范
        if (!str_ends_with($shortName, 'Fixtures')) {
            $errors[] = RuleErrorBuilder::message(
                sprintf(
                    'DataFixture 类名 "%s" 应该以 "Fixtures" 结尾',
                    $shortName
                )
            )
                ->line($node->getOriginalNode()->getStartLine())
                ->tip(sprintf('建议重命名为：%sFixtures', str_replace('Fixture', '', $shortName)))
                ->build()
            ;
        }

        // 检查文件名是否与类名一致
        if ($fileName) {
            $expectedFileName = $shortName . '.php';
            $actualFileName = basename($fileName);

            if ($actualFileName !== $expectedFileName) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        '文件名 "%s" 应该与类名一致，期望为 "%s"',
                        $actualFileName,
                        $expectedFileName
                    )
                )
                    ->line($node->getOriginalNode()->getStartLine())
                    ->build()
                ;
            }

            // 检查文件路径是否包含 DataFixtures 目录
            if (!str_contains($fileName, '/DataFixtures/')) {
                $errors[] = RuleErrorBuilder::message(
                    'DataFixture 文件应该位于 DataFixtures 目录中'
                )
                    ->line($node->getOriginalNode()->getStartLine())
                    ->tip('将文件移动到 src/DataFixtures/ 或 YourBundle/src/DataFixtures/ 目录')
                    ->build()
                ;
            }
        }

        // 检查类名的实体部分是否合理
        if (preg_match('/^(.+?)Fixtures$/', $shortName, $matches)) {
            $entityPart = $matches[1];

            // 检查是否是单数形式（应该是单数） - 已禁用，项目中使用复数形式
            // if ($this->isPluralForm($entityPart)) {
            //     $singular = $this->getSingularForm($entityPart);
            //     $errors[] = RuleErrorBuilder::message(
            //         sprintf(
            //             'DataFixture 类名中的实体部分 "%s" 应该使用单数形式',
            //             $entityPart
            //         )
            //     )
            //         ->line($node->getOriginalNode()->getStartLine())
            //         ->tip(sprintf('建议重命名为：%sFixtures', $singular))
            //         ->build()
            //     ;
            // }

            // 检查命名是否符合 PascalCase
            if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $entityPart)) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        'DataFixture 类名中的实体部分 "%s" 应该使用 PascalCase 格式',
                        $entityPart
                    )
                )
                    ->line($node->getOriginalNode()->getStartLine())
                    ->build()
                ;
            }
        }

        return $errors;
    }

    /**
     * 检查是否是复数形式
     */
    private function isPluralForm(string $word): bool
    {
        // 简单的复数检测规则
        $pluralPatterns = [
            '/ies$/',      // entities, categories
            '/ves$/',      // lives, knives
            '/oes$/',      // heroes, potatoes
            '/ses$/',      // classes, buses
            '/ches$/',     // matches, churches
            '/shes$/',     // dishes, bushes
            '/xes$/',      // boxes, foxes
            '/zes$/',      // buzzes
            '/s$/',        // users, posts (但不包括 ss, us, is 等结尾)
        ];

        // 排除一些常见的单数词
        $singularEndings = ['ss', 'us', 'is', 'as', 'os', 'ics'];
        foreach ($singularEndings as $ending) {
            if (str_ends_with($word, $ending)) {
                return false;
            }
        }

        // 排除网络协议术语（专有名词，不遵循常规单复数规则）
        $protocolTerms = ['ProxySs', 'ProxySsr', 'Vmess', 'Trojan', 'Vless'];
        if (in_array($word, $protocolTerms, true)) {
            return false;
        }

        foreach ($pluralPatterns as $pattern) {
            if (preg_match($pattern, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取单数形式（简化版本）
     */
    private function getSingularForm(string $plural): string
    {
        // 简单的单数转换规则
        $rules = [
            '/ies$/' => 'y',        // entities -> entity
            '/ves$/' => 'fe',       // lives -> life
            '/oes$/' => 'o',        // heroes -> hero
            '/ses$/' => 's',        // classes -> class
            '/ches$/' => 'ch',      // matches -> match
            '/shes$/' => 'sh',      // dishes -> dish
            '/xes$/' => 'x',        // boxes -> box
            '/zes$/' => 'z',        // buzzes -> buzz
            '/s$/' => '',           // users -> user
        ];

        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $plural)) {
                return preg_replace($pattern, $replacement, $plural);
            }
        }

        return $plural;
    }
}
