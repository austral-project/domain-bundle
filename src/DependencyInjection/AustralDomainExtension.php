<?php

/*
 * This file is part of the Austral Domain Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\DomainBundle\DependencyInjection;

use Austral\CacheBundle\Cache\ResultCache;
use Austral\DomainBundle\Entity\Interfaces\DomainInterface;
use Austral\DomainBundle\Services\DomainsManagement;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Austral Domain Extension.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class AustralDomainExtension extends Extension
{
  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function load(array $configs, ContainerBuilder $container): void
  {
    $config = $this->processConfiguration(new Configuration(), $configs);
    $container->setParameter('austral_domain', $config);

    $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
    $loader->load('parameters.yaml');
    $loader->load('services.yaml');
    $loader->load('command.yaml');
    $this->loadBridges($container, $loader);

    if ($config['cache'])
    {
      // The key is deleted by cache-bundle when a domain is saved (EntityCacheListener).
      $container->getDefinition(DomainsManagement::class)
        ->setArgument('$resultCache', new Reference(ResultCache::class))
        ->addTag('austral.cache.result_key', ['key' => DomainsManagement::CACHE_KEY_ENABLED_DOMAINS, 'class' => DomainInterface::class]);
    }
  }

  /**
   * @param ContainerBuilder $container
   * @param YamlFileLoader   $loader
   *
   * @throws \Exception
   */
  protected function loadBridges(ContainerBuilder $container, YamlFileLoader $loader): void
  {
    $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];
    $bundles = is_array($bundles) ? $bundles : [];

    if (in_array('Austral\\AdminBundle\\AustralAdminBundle', $bundles, true))
    {
      $loader->load('bridge/admin.yaml');
    }
  }

  /**
   * @return string
   */
  public function getNamespace(): string
  {
    return 'https://austral.dev/schema/dic/austral_domain';
  }
}
