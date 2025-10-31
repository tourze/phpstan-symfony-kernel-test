<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyKernelTest\Config;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * 禁止实现 Symfony ConfigurationInterface
 * 我们采用在运行时读取环境变量的方式来读取配置
 *
 * @implements Rule<Class_>
 */
class ForbidConfigurationInterfaceRule implements Rule
{
    private const FORBIDDEN_INTERFACE = 'Symfony\Component\Config\Definition\ConfigurationInterface';

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_ || $node->isAnonymous()) {
            return [];
        }

        $errors = [];

        // 检查实现的接口
        foreach ($node->implements as $implement) {
            $interfaceName = $implement->toString();

            // 检查完全限定名称
            if (self::FORBIDDEN_INTERFACE === $interfaceName) {
                $errors[] = RuleErrorBuilder::message(
                    '禁止实现 ConfigurationInterface。请在运行时使用环境变量的方式读取配置，也就是在服务中直接使用 $_ENV'
                )
                    ->line($implement->getLine())
                    ->build()
                ;
            }

            // 检查短名称（已经 use 的情况）
            if ('ConfigurationInterface' === $interfaceName) {
                $reflection = $scope->getClassReflection();
                if (null !== $reflection) {
                    foreach ($reflection->getInterfaces() as $interface) {
                        if (self::FORBIDDEN_INTERFACE === $interface->getName()) {
                            $errors[] = RuleErrorBuilder::message(
                                '禁止实现 ConfigurationInterface。请在运行时使用环境变量的方式读取配置，也就是在服务中直接使用 $_ENV'
                            )
                                ->line($implement->getLine())
                                ->build()
                            ;
                            break;
                        }
                    }
                }
            }
        }

        return $errors;
    }
}
