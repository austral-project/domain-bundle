<?php

/*
 * This file is part of the Austral Domain Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\DomainBundle\Listener;

use Austral\DomainBundle\Services\DomainsManagement;
use Austral\HttpBundle\Services\HttpRequest;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Detects the current domain of the request, and sets the language of the request on the domains.
 *
 * @template T of object
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
class RequestListener
{
  /**
   * @param DomainsManagement<T> $domainsManagement
   * @param HttpRequest<T>       $httpRequest
   */
  public function __construct(
    protected DomainsManagement $domainsManagement,
    protected HttpRequest $httpRequest
  ) {
  }

  /**
   * Priority 40, before the router (32): the routes of seo-bundle depend on the current domain.
   *
   * @param RequestEvent $event
   *
   * @return void
   */
  public function onRequest(RequestEvent $event): void
  {
    if ($event->isMainRequest())
    {
      $this->domainsManagement->resolveCurrentDomain($event->getRequest());
    }
  }

  /**
   * Priority 20, after HttpListener::onRequestInitialise() (30), which reads the language of the request.
   *
   * @param RequestEvent $event
   *
   * @return void
   */
  public function onRequestLanguage(RequestEvent $event): void
  {
    if ($event->isMainRequest())
    {
      $this->domainsManagement->applyRequestLanguage($this->httpRequest->getLanguage());
    }
  }
}
