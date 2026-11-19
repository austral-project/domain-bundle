<?php

/*
 * This file is part of the Austral Domain Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\DomainBundle\DependencyInjection\Compiler;

use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Austral Website Load Doctrine Resolve.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class DoctrineResolveTargetEntityPass implements CompilerPassInterface
{
  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void
  {
    $resolveTargetEntities = $container->getParameter('austral.resolve_target_entities.http');
    if (is_iterable($resolveTargetEntities))
    {
      $definition = $container->findDefinition('doctrine.orm.listeners.resolve_target_entity');
      foreach ($resolveTargetEntities as $from => $to)
      {
        $definition->addMethodCall('addResolveTargetEntity', [$from, $to, []]);
      }
      // DoctrineBundle only registers this listener when "doctrine.orm.resolve_target_entities" is configured.
      $events = array_column($definition->getTag('doctrine.event_listener'), 'event');
      foreach ([Events::loadClassMetadata, Events::onClassMetadataNotFound] as $event)
      {
        if (!in_array($event, $events, true))
        {
          $definition->addTag('doctrine.event_listener', ['event' => $event]);
        }
      }
    }
  }
}
