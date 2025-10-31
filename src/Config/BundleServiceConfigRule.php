<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Config;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Yaml\Yaml;
use Tourze\PHPStanSymfonyKernelTest\Util\BundleChecker;

/**
 * 检查Bundle的services_dev.yaml和services_test.yaml文件
 * 这些文件只能配置DataFixtures相关服务，不能配置其他服务
 *
 * @implements Rule<Class_>
 */
class BundleServiceConfigRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $className = $scope->getNamespace();
        if (null !== $node->name) {
            $className = null !== $className ? $className . '\\' . $node->name->name : $node->name->name;
        }

        if (null === $className) {
            return [];
        }

        // 只检查Bundle类
        if (!BundleChecker::isBundleClass($className)) {
            return [];
        }

        // 只检查Bundle类本身（类名以Bundle结尾）
        if (!str_ends_with($className, 'Bundle')) {
            return [];
        }

        $errors = [];

        // 获取Bundle的根路径
        $bundlePath = $this->getBundlePath($className);
        if (null === $bundlePath) {
            return [];
        }

        // 检查services_dev.yaml
        $devConfigPath = $bundlePath . '/src/Resources/config/services_dev.yaml';
        if (file_exists($devConfigPath)) {
            $devErrors = $this->checkServiceConfig($devConfigPath, 'services_dev.yaml');
            $errors = array_merge($errors, $devErrors);
        }

        // 检查services_test.yaml
        $testConfigPath = $bundlePath . '/src/Resources/config/services_test.yaml';
        if (file_exists($testConfigPath)) {
            $testErrors = $this->checkServiceConfig($testConfigPath, 'services_test.yaml');
            $errors = array_merge($errors, $testErrors);
        }

        return $errors;
    }

    /**
     * 从类名获取Bundle的根路径
     */
    private function getBundlePath(string $className): ?string
    {
        // 从命名空间推断Bundle路径
        $parts = explode('\\', $className);

        if (str_starts_with($className, 'Tourze\\')) {
            // 例如: Tourze\AliyunVodBundle\AliyunVodBundle -> packages/aliyun-vod-bundle
            if (isset($parts[1])) {
                $packageName = $parts[1];
                // 将驼峰命名转换为kebab-case
                // 处理连续大写字母，例如 AIContentAuditBundle -> ai-content-audit-bundle
                $kebabCase = strtolower(preg_replace('/([A-Z])([A-Z][a-z])|([a-z])([A-Z])/', '$1$3-$2$4', $packageName));

                return getcwd() . '/packages/' . $kebabCase;
            }
        } elseif (str_ends_with($className, 'Bundle')) {
            // 例如: AIContentAuditBundle\AIContentAuditBundle -> packages/ai-content-audit-bundle
            // 或者: AIContentAuditBundle -> packages/ai-content-audit-bundle
            $packageName = $parts[0]; // 取第一个部分作为包名
            // 将驼峰命名转换为kebab-case
            // 处理连续大写字母，例如 AIContentAuditBundle -> ai-content-audit-bundle
            $kebabCase = strtolower(preg_replace('/([A-Z])([A-Z][a-z])|([a-z])([A-Z])/', '$1$3-$2$4', $packageName));

            return getcwd() . '/packages/' . $kebabCase;
        }

        return null;
    }

    /**
     * 检查service配置文件
     *
     * @return array<RuleError>
     */
    private function checkServiceConfig(string $configPath, string $fileName): array
    {
        $errors = [];

        try {
            $config = Yaml::parseFile($configPath);
        } catch (\Exception $e) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Bundle服务配置文件解析错误：%s - %s',
                    $fileName,
                    $e->getMessage()
                ))
                    ->build(),
            ];
        }

        if (!isset($config['services'])) {
            return [];
        }

        $services = $config['services'];

        // 忽略_defaults配置
        unset($services['_defaults']);

        foreach ($services as $serviceKey => $serviceConfig) {
            // 检查是否是DataFixtures相关服务
            if (!$this->isDataFixturesService($serviceKey)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Bundle服务配置违规：%s 中的服务 "%s" 不被允许。%s 文件只能配置 DataFixtures 相关服务。',
                    $fileName,
                    $serviceKey,
                    $fileName
                ))
                    ->build()
                ;
            }
        }

        return $errors;
    }

    /**
     * 判断是否是DataFixtures相关服务
     */
    private function isDataFixturesService(string $serviceKey): bool
    {
        // 检查服务key是否包含DataFixtures
        if (str_contains($serviceKey, 'DataFixtures')) {
            return true;
        }

        // 检查服务key是否以DataFixtures结尾（命名空间形式）
        if (str_ends_with($serviceKey, 'DataFixtures\\')) {
            return true;
        }

        // 允许测试用的Mock服务（通常以Mock开头或包含Mock）
        if (str_contains($serviceKey, 'Mock') || str_contains($serviceKey, 'Test')) {
            return true;
        }

        // 允许常见的外部依赖服务（用于测试Mock）
        $allowedExternalServices = [
            'WechatWorkBundle\Service\WorkService',
            'WechatWorkBundle\Service\WorkServiceInterface',
            'Tourze\WechatWorkBundle\Service\WorkService',
        ];

        if (in_array($serviceKey, $allowedExternalServices, true)) {
            return true;
        }

        return false;
    }
}
