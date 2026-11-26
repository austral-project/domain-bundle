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

use Austral\AdminBundle\Module\Module;
use Austral\AdminBundle\Module\ModuleDefinition;
use Austral\AdminBundle\Variant\ModuleVariant;
use Austral\AdminBundle\Variant\ModuleVariantProviderInterface;
use Austral\Contracts\Domain\FilterByDomainInterface;
use Austral\Contracts\File\FileInterface;
use Austral\DomainBundle\Attribute\DomainFilter;
use Austral\DomainBundle\Entity\Interfaces\DomainInterface;
use Austral\DomainBundle\Services\DomainsManagement;
use Austral\EntityFileBundle\File\Link\Generator;
use Doctrine\ORM\QueryBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Austral Domain Variant Provider.
 *
 * One variant by domain for the entity modules whose entity has #[DomainFilter(autoDomainId: true)]: the list of a
 * domain shows its entities only, an entity created there belongs to it (/pages/{domain}/new). The domains are those
 * without the virtual ones and the redirections (the masters and the domains of a language), plus "all the domains"
 * when there are several and the entity allows it (forAllDomainEnabled). With one domain only, admin-bundle skips
 * the choice.
 *
 * Replaces the modules cloned by domain of the 3.1 (ModuleEventSubscriber::moduleAdd()).
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
final class DomainVariantProvider implements ModuleVariantProviderInterface
{
  /**
   * Key of the attributes of a variant with the id of its domain (read by AdminDomainListener).
   */
  public const string ATTRIBUTE_DOMAIN_ID = 'austral_domain_id';

  /**
   * @param DomainsManagement<object> $domainsManagement
   * @param TranslatorInterface       $translator
   * @param Generator<object>         $fileLinkGenerator
   */
  public function __construct(
    private readonly DomainsManagement $domainsManagement,
    private readonly TranslatorInterface $translator,
    private readonly Generator $fileLinkGenerator,
  ) {
  }

  /**
   * @param ModuleDefinition $definition
   *
   * @return bool
   */
  public static function supports(ModuleDefinition $definition): bool
  {
    return true === self::domainFilter($definition->getEntityClass())?->autoDomainId;
  }

  /**
   * The attribute #[DomainFilter] of a class or of one of its parents (the nearest wins).
   *
   * @param string|null $class
   *
   * @return DomainFilter|null
   */
  public static function domainFilter(?string $class): ?DomainFilter
  {
    if (null === $class || !class_exists($class))
    {
      return null;
    }
    for ($reflection = new \ReflectionClass($class); false !== $reflection; $reflection = $reflection->getParentClass())
    {
      foreach ($reflection->getAttributes(DomainFilter::class) as $attribute)
      {
        return $attribute->newInstance();
      }
    }

    return null;
  }

  /**
   * @param Module              $module
   * @param list<ModuleVariant> $parents
   *
   * @return iterable<ModuleVariant>
   */
  public function variants(Module $module, array $parents): iterable
  {
    $domains = $this->domainsManagement->getDomainsWithoutVirtual();
    $masterId = $this->domainsManagement->getDomainMaster()?->getId();
    $keys = [];
    foreach ($domains as $domain)
    {
      $id = $domain->getId();
      $ids = [$id];
      if ($id === $masterId && DomainsManagement::DOMAIN_ID_MASTER !== $id)
      {
        // Entities saved before the master domain existed are attached to "master".
        $ids[] = DomainsManagement::DOMAIN_ID_MASTER;
      }
      yield $this->variant($this->key($domain, $keys), $domain->getName() ?: $id, $id, $ids, $this->image($domain));
    }
    // "All the domains" means something with several domains only.
    if (\count($domains) > 1 && true === self::domainFilter($module->getEntityClass())?->forAllDomainEnabled)
    {
      $name = $this->translator->trans('domains.forAll', [], 'austral');
      yield $this->variant(DomainsManagement::DOMAIN_ID_FOR_ALL_DOMAINS, $name, DomainsManagement::DOMAIN_ID_FOR_ALL_DOMAINS, [DomainsManagement::DOMAIN_ID_FOR_ALL_DOMAINS], null);
    }
  }

  /**
   * @param string       $key
   * @param string       $name
   * @param string       $domainId
   * @param list<string> $ids      Values of domainId shown in the list
   * @param string|null  $image
   *
   * @return ModuleVariant
   */
  private function variant(string $key, string $name, string $domainId, array $ids, ?string $image): ModuleVariant
  {
    return new ModuleVariant(
      $key,
      $name,
      static function (QueryBuilder $queryBuilder, string $alias) use ($ids): void {
        $queryBuilder->andWhere("{$alias}.domainId IN (:austral_domain_ids)")->setParameter('austral_domain_ids', $ids);
      },
      static function (object $object) use ($domainId): void {
        if ($object instanceof FilterByDomainInterface)
        {
          $object->setDomainId($domainId);
        }
      },
      'austral-picto-globe',
      $image,
      [self::ATTRIBUTE_DOMAIN_ID => $domainId],
    );
  }

  /**
   * The keyname of the domain when it can be a segment of the URL and is not used yet, otherwise its id.
   *
   * @param DomainInterface<object> $domain
   * @param array<string, true>     $keys   Keys already given
   *
   * @return string
   */
  private function key(DomainInterface $domain, array &$keys): string
  {
    $key = (string) $domain->getKeyname();
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $key) || isset($keys[$key]) || DomainsManagement::DOMAIN_ID_FOR_ALL_DOMAINS === $key)
    {
      $key = $domain->getId();
    }
    $keys[$key] = true;

    return $key;
  }

  /**
   * @param DomainInterface<object> $domain
   *
   * @return string|null URL of the favicon of the domain
   */
  private function image(DomainInterface $domain): ?string
  {
    if (!$domain instanceof FileInterface || !$domain->getFavicon())
    {
      return null;
    }
    try
    {
      return $this->fileLinkGenerator->image($domain, 'favicon');
    }
    catch (\Throwable)
    {
      return null;
    }
  }
}
