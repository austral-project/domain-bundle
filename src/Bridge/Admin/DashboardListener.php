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

use Austral\AdminBundle\Event\DashboardEvent;
use Austral\DomainBundle\Services\DomainsManagement;

/**
 * Austral Dashboard Listener.
 *
 * Block "domains" of the dashboard: the number of domains, on a site with several master domains.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class DashboardListener
{
  public const string BLOCK = 'domains';
  public const string TEMPLATE = '@AustralDomain/admin/dashboard.html.twig';

  /**
   * @param DomainsManagement<object> $domainsManagement
   */
  public function __construct(private readonly DomainsManagement $domainsManagement)
  {
  }

  /**
   * Listener of austral.admin.dashboard.
   *
   * @param DashboardEvent $event
   *
   * @return void
   */
  public function dashboard(DashboardEvent $event): void
  {
    if ($this->domainsManagement->isMultiDomain())
    {
      $event->addBlock(self::BLOCK, self::TEMPLATE, ['count' => \count($this->domainsManagement->getDomainsWithoutVirtual())], 10);
    }
  }
}
