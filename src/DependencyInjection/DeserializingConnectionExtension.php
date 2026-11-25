<?php

declare(strict_types=1);

namespace BuronDigital\DeserializingConnection\DependencyInjection;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * @codeCoverageIgnore
 */
final class DeserializingConnectionExtension extends Extension
{
    /**
     * @param array<mixed> $configs
     */
    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        // The ResultSetMappingConnection is only available with doctrine/orm, which is an optional dependency.
        if (interface_exists(EntityManagerInterface::class)) {
            $loader->load('orm.yaml');
        }

        /** @var array{profiling: bool} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('deserializing_connection.profiling', $config['profiling']);
    }
}
