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

namespace Austral\DomainBundle\Tests\Listener;

use Austral\DomainBundle\Listener\RequestListener;
use Austral\DomainBundle\Services\DomainsManagement;
use Austral\HttpBundle\Configuration\HttpConfiguration;
use Austral\HttpBundle\Services\HttpRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RequestListenerTest extends TestCase
{
  /**
   * @return iterable<string, array{int, int}>
   */
  public static function requestTypes(): iterable
  {
    yield 'main request' => [HttpKernelInterface::MAIN_REQUEST, 1];
    yield 'sub-request' => [HttpKernelInterface::SUB_REQUEST, 0];
  }

  #[DataProvider('requestTypes')]
  public function testTheCurrentDomainIsDetectedOnTheMainRequestOnly(int $requestType, int $calls): void
  {
    $request = Request::create('https://www.a.test/');
    $domainsManagement = $this->createMock(DomainsManagement::class);
    $domainsManagement->expects(self::exactly($calls))
      ->method('resolveCurrentDomain')
      ->with(self::identicalTo($request))
      ->willReturnSelf();

    $this->listener($domainsManagement)->onRequest($this->event($request, $requestType));
  }

  #[DataProvider('requestTypes')]
  public function testTheLanguageOfTheRequestIsSetOnTheMainRequestOnly(int $requestType, int $calls): void
  {
    $domainsManagement = $this->createMock(DomainsManagement::class);
    $domainsManagement->expects(self::exactly($calls))
      ->method('applyRequestLanguage')
      ->with('fr')
      ->willReturnSelf();

    $this->listener($domainsManagement)->onRequestLanguage($this->event(Request::create('https://www.a.test/'), $requestType));
  }

  /**
   * @param DomainsManagement<object> $domainsManagement
   *
   * @return RequestListener<object>
   */
  private function listener(DomainsManagement $domainsManagement): RequestListener
  {
    $httpRequest = new HttpRequest(new HttpConfiguration());
    $httpRequest->setLanguage('fr');

    return new RequestListener($domainsManagement, $httpRequest);
  }

  private function event(Request $request, int $requestType): RequestEvent
  {
    return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $requestType);
  }
}
