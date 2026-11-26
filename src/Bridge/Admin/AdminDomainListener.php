<?php

/*
 * This file is part of the Austral Domain Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\DomainBundle\Bridge\Admin;

use Austral\AdminBundle\Event\AdminHttpEvent;
use Austral\AdminBundle\EventSubscriber\HttpAdminEventSubscriber;
use Austral\AdminBundle\Handler\AdminHandler;
use Austral\AdminBundle\Module\Module;
use Austral\DomainBundle\Services\DomainsManagement;
use Austral\HttpBundle\Event\Interfaces\HttpEventInterface;

/**
 * Austral Admin Domain Listener.
 *
 * In a variant of a domain (DomainVariantProvider), the filter domain is the domain of the variant for the whole
 * request, as in the 3.1: DomainAttachment attaches to it the entities saved without domain and their children.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class AdminDomainListener
{
  /**
   * After HttpAdminEventSubscriber::onRequest(), which resolves the module of the request.
   */
  public const int PRIORITY = HttpAdminEventSubscriber::PRIORITY - 1;

  /**
   * @param DomainsManagement<object> $domainsManagement
   */
  public function __construct(private readonly DomainsManagement $domainsManagement)
  {
  }

  /**
   * Listener of austral.event.http.request.
   *
   * @param HttpEventInterface<object> $event
   *
   * @return void
   */
  public function onRequest(HttpEventInterface $event): void
  {
    $handler = $event->getHandler();
    if ($event instanceof AdminHttpEvent && $handler instanceof AdminHandler && $handler->hasModule())
    {
      $this->applyFilterDomain($handler->getModule());
    }
  }

  /**
   * @param Module $module
   *
   * @return void
   */
  private function applyFilterDomain(Module $module): void
  {
    foreach ($module->getVariants() as $variant)
    {
      $domainId = $variant->attributes[DomainVariantProvider::ATTRIBUTE_DOMAIN_ID] ?? null;
      if (\is_string($domainId))
      {
        $this->domainsManagement->setFilterDomainId($domainId);
      }
    }
  }
}
