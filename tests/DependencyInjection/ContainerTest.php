<?php

/*
 * This file is part of the Austral Domain Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Austral\DomainBundle\Tests\DependencyInjection;

use Austral\AdminBundle\DependencyInjection\AustralAdminExtension;
use Austral\CacheBundle\AustralCacheBundle;
use Austral\CacheBundle\Cache\ResultCache;
use Austral\CacheBundle\DependencyInjection\AustralCacheExtension;
use Austral\CacheBundle\Event\CacheInvalidatedEvent;
use Austral\CacheBundle\Event\CacheRebuildEvent;
use Austral\CacheBundle\Listener\EntityCacheListener;
use Austral\DomainBundle\AustralDomainBundle;
use Austral\DomainBundle\Bridge\Admin\AdminDomainListener;
use Austral\DomainBundle\Bridge\Admin\DashboardListener;
use Austral\DomainBundle\Bridge\Admin\DomainAdmin;
use Austral\DomainBundle\Bridge\Admin\DomainVariantProvider;
use Austral\DomainBundle\Cache\DomainCacheContext;
use Austral\DomainBundle\Command\DomainAttachementMigrateCommand;
use Austral\DomainBundle\DependencyInjection\AustralDomainExtension;
use Austral\DomainBundle\Entity\Interfaces\DomainInterface;
use Austral\DomainBundle\EntityManager\DomainEntityManager;
use Austral\DomainBundle\Listener\CacheListener;
use Austral\DomainBundle\Listener\DoctrineListener;
use Austral\DomainBundle\Listener\EntityManagerListener;
use Austral\DomainBundle\Listener\EntityMappingListener;
use Austral\DomainBundle\Listener\RequestListener;
use Austral\DomainBundle\Services\DomainAttachment;
use Austral\DomainBundle\Services\DomainsManagement;
use Austral\DomainBundle\Tests\Fixtures\TestDomain;
use Austral\EntityBundle\EntityManager\EntityManager;
use Austral\EntityBundle\Event\EntityManagerEvent;
use Austral\EntityBundle\Event\EntityMappingEvent;
use Austral\EntityBundle\Mapping\Mapping;
use Austral\EntityFileBundle\File\Link\Generator;
use Austral\HttpBundle\Configuration\HttpConfiguration;
use Austral\HttpBundle\Services\HttpRequest;
use Austral\ToolsBundle\Services\Debug;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The services are defined explicitly: a missing or misnamed argument only shows up when the container
 * is compiled or the service is created. This test loads the extension (with cache-bundle, a dependency
 * of the bundle), compiles the container and creates every service of the bundle, with and without the
 * bridge of admin-bundle.
 */
final class ContainerTest extends TestCase
{
  private const string ADMIN_BUNDLE = 'Austral\\AdminBundle\\AustralAdminBundle';

  private const string RESOLVE_TARGET_ENTITY = 'doctrine.orm.listeners.resolve_target_entity';

  /**
   * Services of the other bundles used by the bridge of admin-bundle, set after the compilation.
   */
  private const array ADMIN_SERVICES = [
    'security.authorization_checker' => AuthorizationCheckerInterface::class,
    'translator' => TranslatorInterface::class,
    'austral.entity_file.link.generator' => Generator::class,
  ];

  /**
   * Public aliases: the supported way to get a service from the container.
   */
  private const array PUBLIC_ALIASES = [
    'austral.domain.management' => DomainsManagement::class,
    'austral.entity_manager.domain' => DomainEntityManager::class,
  ];

  /**
   * @return iterable<string, array{class-string, bool}>
   */
  public static function serviceIds(): iterable
  {
    foreach ([false, true] as $withAdmin)
    {
      foreach (self::bundleServices($withAdmin) as $class)
      {
        yield ($withAdmin ? 'with admin: ' : '').$class => [$class, $withAdmin];
      }
    }
  }

  /**
   * @param class-string $class
   */
  #[DataProvider('serviceIds')]
  public function testEveryServiceCanBeCreated(string $class, bool $withAdmin): void
  {
    self::assertInstanceOf($class, $this->compiledContainer(withAdmin: $withAdmin)->get($class));
  }

  public function testTheBundleDefinesNoOtherService(): void
  {
    foreach ([false, true] as $withAdmin)
    {
      $ids = [];
      foreach (self::loadExtension(withAdmin: $withAdmin)->getDefinitions() as $id => $definition)
      {
        if (str_starts_with($id, 'Austral\\DomainBundle\\'))
        {
          $ids[] = $id;
        }
      }
      sort($ids);

      $expected = self::bundleServices($withAdmin);
      sort($expected);

      self::assertSame($expected, $ids);
    }
  }

  public function testTheAdminBridgeIsLoadedOnlyWithAdminBundle(): void
  {
    $withoutAdmin = self::loadExtension();
    $withAdmin = self::loadExtension(withAdmin: true);

    self::assertFalse($withoutAdmin->hasDefinition(DashboardListener::class));
    self::assertTrue($withAdmin->getDefinition(DomainAdmin::class)->hasTag('austral.admin'));
    self::assertSame([['priority' => 100]], $withAdmin->getDefinition(DomainVariantProvider::class)->getTag('austral.admin.module_variants'));
    self::assertSame(
      [['event' => 'austral.admin.dashboard', 'method' => 'dashboard']],
      $withAdmin->getDefinition(DashboardListener::class)->getTag('kernel.event_listener')
    );
    self::assertSame(
      [['event' => 'austral.event.http.request', 'method' => 'onRequest', 'priority' => AdminDomainListener::PRIORITY]],
      $withAdmin->getDefinition(AdminDomainListener::class)->getTag('kernel.event_listener')
    );
  }

  public function testTheModuleOfTheDomainsIsAddedToTheAdmin(): void
  {
    $container = new ContainerBuilder();
    new AustralDomainExtension()->prepend($container);
    self::assertSame([], $container->getExtensionConfig('austral_admin'), 'Without admin-bundle: nothing.');

    $container->registerExtension(new AustralAdminExtension());
    new AustralDomainExtension()->prepend($container);
    $module = $container->getExtensionConfig('austral_admin')[0]['modules']['domain'] ?? null;

    self::assertIsArray($module);
    self::assertSame(DomainAdmin::class, $module['class']);
    self::assertSame('austral.entity_manager.domain', $module['entity_manager']);
    self::assertSame('domain', $module['route']);
  }

  public function testOnlyTheDocumentedAliasesArePublic(): void
  {
    $container = self::loadExtension(withAdmin: true);

    foreach ($container->getDefinitions() as $id => $definition)
    {
      if (str_starts_with($id, 'Austral\\DomainBundle\\'))
      {
        self::assertFalse($definition->isPublic(), sprintf('The service "%s" must be private.', $id));
      }
    }

    $aliases = [];
    foreach ($container->getAliases() as $id => $alias)
    {
      if (str_starts_with((string) $alias, 'Austral\\DomainBundle\\'))
      {
        self::assertTrue($alias->isPublic(), sprintf('The alias "%s" must be public.', $id));
        $aliases[$id] = (string) $alias;
      }
    }
    ksort($aliases);

    self::assertSame(self::PUBLIC_ALIASES, $aliases);
  }

  public function testThePublicAliasesCanBeFetched(): void
  {
    $container = $this->compiledContainer(publicServices: false);

    foreach (self::PUBLIC_ALIASES as $alias => $class)
    {
      self::assertInstanceOf($class, $container->get($alias));
    }
  }

  public function testTheConfigurationHasItsDefaults(): void
  {
    self::assertSame(['cache' => true], self::loadExtension()->getParameter('austral_domain'));
  }

  public function testTheEnabledDomainsAreKeptInTheResultCacheByDefault(): void
  {
    $definition = self::loadExtension()->getDefinition(DomainsManagement::class);

    self::assertEquals(new Reference(ResultCache::class), $definition->getArgument('$resultCache'));
    self::assertSame(
      [['key' => DomainsManagement::CACHE_KEY_ENABLED_DOMAINS, 'class' => DomainInterface::class]],
      $definition->getTag('austral.cache.result_key')
    );

    $container = $this->compiledContainer();
    self::assertInstanceOf(ResultCache::class, self::property($container->get(DomainsManagement::class), 'resultCache'));
    // cache-bundle deletes the key when a domain is saved
    self::assertSame(
      [DomainInterface::class => [DomainsManagement::CACHE_KEY_ENABLED_DOMAINS]],
      self::property($container->get(EntityCacheListener::class), 'resultKeys')
    );
  }

  public function testTheCacheOfTheDomainsCanBeDisabled(): void
  {
    $definition = self::loadExtension([['cache' => false]])->getDefinition(DomainsManagement::class);

    self::assertNull($definition->getArgument('$resultCache'));
    self::assertFalse($definition->hasTag('austral.cache.result_key'));

    $container = $this->compiledContainer([['cache' => false]]);
    self::assertNull(self::property($container->get(DomainsManagement::class), 'resultCache'));
    self::assertSame([], self::property($container->get(EntityCacheListener::class), 'resultKeys'));
  }

  public function testTheCurrentDomainIsPartOfTheKeyOfTheRenders(): void
  {
    self::assertTrue(self::loadExtension()->getDefinition(DomainCacheContext::class)->hasTag('austral.cache.context_provider'));
  }

  public function testTheListenersListenToTheirEvents(): void
  {
    $container = self::loadExtension();

    self::assertSame([
      ['event' => 'kernel.request', 'method' => 'onRequest', 'priority' => 40],
      ['event' => 'kernel.request', 'method' => 'onRequestLanguage', 'priority' => 20],
    ], $container->getDefinition(RequestListener::class)->getTag('kernel.event_listener'));
    self::assertSame([
      ['event' => CacheInvalidatedEvent::EVENT_INVALIDATED, 'method' => 'onInvalidated'],
      ['event' => CacheRebuildEvent::EVENT_REBUILD, 'method' => 'onRebuild'],
    ], $container->getDefinition(CacheListener::class)->getTag('kernel.event_listener'));
    self::assertSame(
      [['event' => EntityMappingEvent::EVENT_AUSTRAL_ENTITY_INITIALISE_MAPPING, 'method' => 'mapping']],
      $container->getDefinition(EntityMappingListener::class)->getTag('kernel.event_listener')
    );
    self::assertSame(
      [['event' => EntityManagerEvent::EVENT_DUPLICATE, 'method' => 'duplicate']],
      $container->getDefinition(EntityManagerListener::class)->getTag('kernel.event_listener')
    );
  }

  /**
   * A Doctrine event listener (not a subscriber), on every connection.
   */
  public function testTheDoctrineListenerListensToOnFlush(): void
  {
    $container = self::loadExtension();

    self::assertSame([['event' => 'onFlush']], $container->getDefinition(DoctrineListener::class)->getTag('doctrine.event_listener'));
    self::assertSame([], $container->findTaggedServiceIds('doctrine.event_subscriber'));
  }

  public function testTheStateIsResetBetweenTwoRequests(): void
  {
    $container = self::loadExtension();

    self::assertSame([['method' => 'reset']], $container->getDefinition(DomainsManagement::class)->getTag('kernel.reset'));
    self::assertSame([['method' => 'reset']], $container->getDefinition(DomainAttachment::class)->getTag('kernel.reset'));
  }

  public function testTheCommandIsRegisteredWithItsNameAndTheEventDispatcher(): void
  {
    $container = $this->compiledContainer();

    $tags = $container->findTaggedServiceIds('console.command');
    self::assertSame('austral:domain:migrate', $tags[DomainAttachementMigrateCommand::class][0]['command'] ?? null);

    $command = $container->get(DomainAttachementMigrateCommand::class);
    self::assertInstanceOf(DomainAttachementMigrateCommand::class, $command);
    self::assertInstanceOf(EventDispatcherInterface::class, self::property($command, 'eventDispatcher'));
  }

  public function testTheDomainInterfaceIsResolvedToTheDomainEntityOfTheApplication(): void
  {
    $definition = $this->compiledContainer()->getDefinition(self::RESOLVE_TARGET_ENTITY);

    self::assertSame([['addResolveTargetEntity', [DomainInterface::class, TestDomain::class, []]]], $definition->getMethodCalls());
    self::assertSame(
      [Events::loadClassMetadata, Events::onClassMetadataNotFound],
      array_column($definition->getTag('doctrine.event_listener'), 'event')
    );
  }

  /**
   * DoctrineBundle registers the listener itself when "doctrine.orm.resolve_target_entities" is configured.
   */
  public function testTheDoctrineEventsOfTheResolveTargetEntityListenerAreNotDuplicated(): void
  {
    $container = self::loadExtension();
    $container->getDefinition(self::RESOLVE_TARGET_ENTITY)->addTag('doctrine.event_listener', ['event' => Events::loadClassMetadata]);
    $container->compile();

    self::assertSame(
      [Events::loadClassMetadata, Events::onClassMetadataNotFound],
      array_column($container->getDefinition(self::RESOLVE_TARGET_ENTITY)->getTag('doctrine.event_listener'), 'event')
    );
  }

  public function testTheXmlNamespace(): void
  {
    self::assertSame('https://austral.dev/schema/dic/austral_domain', new AustralDomainExtension()->getNamespace());
  }

  /**
   * @return list<class-string>
   */
  private static function bundleServices(bool $withAdmin): array
  {
    $services = [
      DomainsManagement::class,
      DomainAttachment::class,
      DomainEntityManager::class,
      DomainCacheContext::class,
      RequestListener::class,
      DoctrineListener::class,
      CacheListener::class,
      EntityMappingListener::class,
      EntityManagerListener::class,
      DomainAttachementMigrateCommand::class,
    ];
    if ($withAdmin)
    {
      $services[] = DomainAdmin::class;
      $services[] = DomainVariantProvider::class;
      $services[] = AdminDomainListener::class;
      $services[] = DashboardListener::class;
    }

    return $services;
  }

  private static function property(?object $object, string $name): mixed
  {
    self::assertNotNull($object);

    return new \ReflectionProperty($object, $name)->getValue($object);
  }

  /**
   * The services that the bundle needs from the application are added as minimal definitions: the entity
   * mapping and the entity managers are set after the compilation. cache-bundle is loaded for real.
   *
   * @param list<array<string, mixed>> $configs
   */
  private static function loadExtension(array $configs = [], bool $withAdmin = false): ContainerBuilder
  {
    $container = new ContainerBuilder();
    $container->setParameter('kernel.bundles', $withAdmin ? ['AustralAdminBundle' => self::ADMIN_BUNDLE] : []);
    $container->setDefinition('event_dispatcher', new Definition(EventDispatcher::class));
    $container->setDefinition('request_stack', new Definition(RequestStack::class));
    $container->setDefinition('austral.tools.debug', new Definition(Debug::class));
    $container->setDefinition('austral.http.request', new Definition(HttpRequest::class, [new Definition(HttpConfiguration::class)]));
    foreach (['austral.entity.mapping' => Mapping::class, 'austral.entity_manager' => EntityManager::class, 'doctrine.orm.entity_manager' => EntityManagerInterface::class] as $id => $class)
    {
      $container->setDefinition($id, new Definition($class)->setSynthetic(true)->setPublic(true));
    }
    if ($withAdmin)
    {
      // Services of the application and of the other bundles used by the bridge
      $container->setDefinition('austral.http.config', new Definition(HttpConfiguration::class));
      foreach (self::ADMIN_SERVICES as $id => $class)
      {
        $container->setDefinition($id, new Definition($class)->setSynthetic(true)->setPublic(true));
      }
    }
    // Public: kept after the compilation, to read what the compiler pass of the bundle added
    $container->setDefinition(self::RESOLVE_TARGET_ENTITY, new Definition(ResolveTargetEntityListener::class)->setPublic(true));

    new AustralCacheBundle()->build($container);
    new AustralCacheExtension()->load([], $container);
    new AustralDomainBundle()->build($container);
    new AustralDomainExtension()->load($configs, $container);
    // Set by the application (parameters.yaml points to App\Entity\Austral\DomainBundle\Domain)
    $container->setParameter('austral.entity.domain.class', TestDomain::class);

    return $container;
  }

  /**
   * @param list<array<string, mixed>> $configs
   */
  private function compiledContainer(array $configs = [], bool $withAdmin = false, bool $publicServices = true): ContainerBuilder
  {
    $container = self::loadExtension($configs, $withAdmin);
    if ($publicServices)
    {
      foreach ($container->getDefinitions() as $id => $definition)
      {
        if (str_starts_with($id, 'Austral\\'))
        {
          $definition->setPublic(true);
        }
      }
    }
    $container->compile();
    // Only stored by the services of the bundle: no real instance is needed
    $container->set('austral.entity.mapping', new \ReflectionClass(Mapping::class)->newInstanceWithoutConstructor());
    $container->set('austral.entity_manager', $this->createStub(EntityManager::class));
    $container->set('doctrine.orm.entity_manager', $this->createStub(EntityManagerInterface::class));
    if ($withAdmin)
    {
      $container->set('security.authorization_checker', $this->createStub(AuthorizationCheckerInterface::class));
      $container->set('translator', $this->createStub(TranslatorInterface::class));
      $container->set('austral.entity_file.link.generator', new \ReflectionClass(Generator::class)->newInstanceWithoutConstructor());
    }

    return $container;
  }
}
