<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\DataFixture;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassNode>
 */
class DataFixturesConfigurationRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        if (!$this->isDataFixtureClass($classReflection)) {
            return [];
        }

        $errors = [];

        // 检查是否在 Bundle 目录中
        if (!$this->isInBundleDirectory($classReflection)) {
            return $errors;
        }

        // 获取 Bundle 根目录
        $bundleRootPath = $this->getBundleRootPath($classReflection);
        if (!$bundleRootPath) {
            return $errors;
        }

        // 检查是否有 services_dev.yaml 和 services_test.yaml
        $servicesDevPath = $bundleRootPath . '/src/Resources/config/services_dev.yaml';
        $servicesTestPath = $bundleRootPath . '/src/Resources/config/services_test.yaml';

        if (!file_exists($servicesDevPath) || !file_exists($servicesTestPath)) {
            $errors[] = RuleErrorBuilder::message(
                sprintf(
                    '在 Bundle 中发现 DataFixtures 类 "%s"，但缺少环境特定的服务配置文件。' .
                    '请确保 services_dev.yaml 和 services_test.yaml 存在于 Resources/config/ 目录中。' .
                    '参考实现：packages/user-agreement-bundle/src/DependencyInjection/UserAgreementExtension.php，' .
                    'packages/user-agreement-bundle/src/Resources/config/services_dev.yaml，' .
                    'packages/user-agreement-bundle/src/Resources/config/services_test.yaml。' .
                    'DataFixtures 服务应该在 services_dev.yaml 和 services_test.yaml 中声明，' .
                    '而不是在 services.yaml 中，以避免 composer install --no-dev 时出现问题。' .
                    '注意：您还需要修改对应的 Extension 类，参考 packages/lottery-bundle/src/DependencyInjection/LotteryExtension.php 的实现。',
                    $classReflection->getName()
                )
            )->build();
        } else {
            // 检查 services.yaml 是否包含 DataFixtures 相关配置
            $servicesYamlPath = $bundleRootPath . '/src/Resources/config/services.yaml';
            if (file_exists($servicesYamlPath)) {
                $errors = array_merge($errors, $this->checkServicesYamlForDataFixtures($servicesYamlPath, $classReflection));
            }
        }

        return $errors;
    }

    private function isDataFixtureClass(ClassReflection $classReflection): bool
    {
        // 检查是否继承了 Doctrine\Bundle\FixturesBundle\Fixture
        if ($classReflection->isSubclassOf('Doctrine\Bundle\FixturesBundle\Fixture')) {
            return true;
        }

        // 检查命名空间是否包含 DataFixtures
        return str_contains($classReflection->getName(), 'DataFixtures');
    }

    private function isInBundleDirectory(ClassReflection $classReflection): bool
    {
        $fileName = $classReflection->getFileName();
        if (!$fileName) {
            return false;
        }

        // 检查文件路径是否包含 *-bundle/src
        return (bool) preg_match('/.*-bundle\/src\//', $fileName);
    }

    private function getBundleRootPath(ClassReflection $classReflection): ?string
    {
        $fileName = $classReflection->getFileName();
        if (!$fileName) {
            return null;
        }

        // 从文件路径中提取 Bundle 根目录
        if (preg_match('/^(.*-bundle)\/src\//', $fileName, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function checkServicesYamlForDataFixtures(string $servicesYamlPath, ClassReflection $classReflection): array
    {
        $errors = [];
        $content = file_get_contents($servicesYamlPath);

        if (!$content) {
            return $errors;
        }

        // 获取 DataFixtures 命名空间
        $namespace = $classReflection->getName();
        $namespaceParts = explode('\\', $namespace);

        // 查找 DataFixtures 部分
        $dataFixturesIndex = array_search('DataFixtures', $namespaceParts);
        if (false === $dataFixturesIndex) {
            return $errors;
        }

        // 构建可能的命名空间模式
        $bundleNamespace = implode('\\', array_slice($namespaceParts, 0, $dataFixturesIndex + 1));

        // 检查 services.yaml 是否包含 DataFixtures 相关配置
        if (str_contains($content, $bundleNamespace) || str_contains($content, 'DataFixtures')) {
            $errors[] = RuleErrorBuilder::message(
                sprintf(
                    '在包含 "%s" 的 Bundle 的 services.yaml 中发现了 DataFixtures 服务。' .
                    'DataFixtures 服务应该移至 services_dev.yaml 和 services_test.yaml，' .
                    '以避免 composer install --no-dev 时出现问题。' .
                    '参考实现：packages/user-agreement-bundle/src/Resources/config/。' .
                    '同时需要修改对应的 Extension 类来加载这些配置文件。',
                    $classReflection->getName()
                )
            )->build();
        }

        return $errors;
    }
}
