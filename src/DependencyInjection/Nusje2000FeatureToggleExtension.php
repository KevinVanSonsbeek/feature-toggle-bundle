<?php

declare(strict_types=1);

namespace Nusje2000\FeatureToggleBundle\DependencyInjection;

use Nusje2000\FeatureToggleBundle\Feature\SimpleFeature;
use Nusje2000\FeatureToggleBundle\Feature\State;
use Nusje2000\FeatureToggleBundle\Repository\EnvironmentRepository;
use Nusje2000\FeatureToggleBundle\Repository\FeatureRepository;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\CachingHttpClient;

final class Nusje2000FeatureToggleExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $xmlLoader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config/services'));
        $xmlLoader->load('cache.xml');
        $xmlLoader->load('controllers.xml');
        $xmlLoader->load('http.xml');
        $xmlLoader->load('subscribers.xml');

        /** @var array{
         *      repository: array{
         *          enabled: bool,
         *          service: array{
         *              enabled: bool,
         *              feature: string,
         *              environment: string
         *          },
         *          static: bool|null,
         *          remote: array{
         *              enabled: bool,
         *              cache_store: string|null,
         *              host: string,
         *              base_path: string
         *          }
         *      },
         *      environment: array{
         *          enabled: bool,
         *          name: string,
         *          hosts: list<string>,
         *          features: array<array-key, bool>
         *      }
         *  } $config
         */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $this->configureRepository($container, $xmlLoader, $config['repository']);
        $this->configureEnvironment($container, $xmlLoader, $config['environment']);
    }

    /**
     * @param array{
     *     enabled: bool,
     *     name: string,
     *     hosts: list<string>,
     *     features: array<array-key, bool>
     * } $config
     */
    private function configureEnvironment(ContainerBuilder $container, XmlFileLoader $xmlLoader, array $config): void
    {
        if (!$config['enabled']) {
            return;
        }

        $container->setParameter('nusje2000_feature_toggle.environment_name', $config['name']);
        $container->setParameter('nusje2000_feature_toggle.hosts', $config['hosts']);
        $container->setParameter('nusje2000_feature_toggle.feature_defaults', $config['features']);

        $xmlLoader->load('environment.xml');

        foreach ($config['features'] as $name => $enabled) {
            $container->getDefinition('nusje2000_feature_toggle.default_environment')->addMethodCall('addFeature', [
                new Definition(SimpleFeature::class, [
                    (string) $name,
                    new Definition(State::class, [
                        State::fromBoolean($enabled)->getValue(),
                    ]),
                ]),
            ]);
        }

        if ($container->hasDefinition('nusje2000_feature_toggle.repository.environment.static')) {
            $container->getDefinition('nusje2000_feature_toggle.repository.environment.static')->setArguments([
                [new Reference('nusje2000_feature_toggle.default_environment')],
            ]);
        }
    }

    /**
     * @param array{
     *     enabled: bool|null,
     *     service: array{
     *         enabled: bool,
     *         feature: string,
     *         environment: string
     *     },
     *     static: bool|null,
     *     remote: array{
     *         enabled: bool,
     *         host: string,
     *         cache_store: string|null,
     *         base_path: string
     *     }
     * } $config
     */
    private function configureRepository(ContainerBuilder $container, XmlFileLoader $xmlLoader, array $config): void
    {
        if (true === $config['static']) {
            $xmlLoader->load('repository/static.xml');

            return;
        }
        if (true === $config['remote']['enabled']) {
            $container->setParameter('nusje2000_feature_toggle.remote.host', $config['remote']['host']);
            $container->setParameter('nusje2000_feature_toggle.remote.base_path', $config['remote']['base_path']);

            $xmlLoader->load('repository/remote.xml');

            if (null !== $config['remote']['cache_store']) {
                $container->setDefinition('nusje2000_feature_toggle.http_client.caching', new Definition(
                    CachingHttpClient::class,
                    [
                        new Reference('nusje2000_feature_toggle.http_client.scoping'),
                        new Reference($config['remote']['cache_store']),
                    ]
                ));

                $container->setAlias('nusje2000_feature_toggle.http_client', 'nusje2000_feature_toggle.http_client.caching');
            }

            return;
        }

        if ($config['service']['enabled']) {
            $container->addAliases([
                'nusje2000_feature_toggle.repository.environment' => $config['service']['environment'],
                'nusje2000_feature_toggle.repository.feature' => $config['service']['feature'],
            ]);

            return;
        }

        $xmlLoader->load('repository/static.xml');
    }
}
