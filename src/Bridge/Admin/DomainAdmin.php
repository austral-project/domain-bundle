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

use Austral\AdminBundle\Admin\Admin;
use Austral\AdminBundle\Event\AdminEvents;
use Austral\AdminBundle\Event\FilterAdminEvent;
use Austral\AdminBundle\Event\FormAdminEvent;
use Austral\AdminBundle\Event\ListAdminEvent;
use Austral\AdminBundle\Event\QueryAdminEvent;
use Austral\DomainBundle\Entity\Interfaces\DomainInterface;
use Austral\FilterBundle\Filter\SearchFilter;
use Austral\FormBundle\Field\ChoiceField;
use Austral\FormBundle\Field\EntityField;
use Austral\FormBundle\Field\SelectField;
use Austral\FormBundle\Field\SwitchField;
use Austral\FormBundle\Field\TextField;
use Austral\FormBundle\Field\UploadField;
use Austral\FormBundle\Layout\Fieldset;
use Austral\FormBundle\Layout\Group;
use Austral\HttpBundle\Configuration\HttpConfiguration;
use Austral\ListBundle\Column\DateColumn;
use Austral\ListBundle\Column\SwitchColumn;
use Austral\ListBundle\Column\ValueColumn;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Austral Domain Admin.
 *
 * Module "domain": the domains of the site. A user who is not root sees and edits the master domains only, and does
 * not see the key of a domain.
 *
 * @author Matthieu Beurel <matthieu@austral.dev>
 */
class DomainAdmin extends Admin
{
  /**
   * Values of the switches of the list.
   */
  private const array TOGGLES = ['isEnabled', 'isMaster', 'isVirtual', 'onePage'];

  /**
   * @param AuthorizationCheckerInterface $authorizationChecker
   * @param HttpConfiguration             $httpConfiguration    Its "env.list" gives the environments of a domain
   */
  public function __construct(
    private readonly AuthorizationCheckerInterface $authorizationChecker,
    private readonly HttpConfiguration $httpConfiguration,
  ) {
  }

  /**
   * @return array<string, string>
   */
  public function getEvents(): array
  {
    return [AdminEvents::FORM_SAVE_BEFORE => 'formSaveBefore'];
  }

  /**
   * The restriction applies to every query of the module (list, edit, toggle, export...).
   *
   * @param QueryAdminEvent $event
   *
   * @return void
   */
  public function configureQuery(QueryAdminEvent $event): void
  {
    $queryBuilder = $event->getQueryBuilder();
    $alias = $event->getRootAlias();
    if (!$this->isRoot())
    {
      $queryBuilder->andWhere("{$alias}.isMaster = :austral_domain_is_master")->setParameter('austral_domain_is_master', true);
    }
    if ($event->is(QueryAdminEvent::CONTEXT_LIST) || $event->is(QueryAdminEvent::CONTEXT_EXPORT))
    {
      $queryBuilder->orderBy("{$alias}.position", 'ASC')->addOrderBy("{$alias}.name", 'ASC');
    }
  }

  /**
   * @param ListAdminEvent $event
   *
   * @return void
   */
  public function configureListMapper(ListAdminEvent $event): void
  {
    $mapper = $event->getMapper()
      ->add(new ValueColumn('name', ['label' => 'fields.nameDomain.entitled']))
      ->add(new ValueColumn('domain'))
      ->add(new ValueColumn('domainEnv'));
    foreach (self::TOGGLES as $field)
    {
      $toggle = $event->toggle($field);
      $mapper->add(new SwitchColumn($field, ['url' => $toggle['url'], 'granted' => $toggle['granted']]));
    }
    $mapper->add(new DateColumn('updated', ['date_format' => 'd/m/Y']));
  }

  /**
   * @param FilterAdminEvent $event
   *
   * @return void
   */
  public function configureFilterMapper(FilterAdminEvent $event): void
  {
    $event->getMapper()->filter()->add(new SearchFilter('search', ['properties' => ['name', 'domain', 'keyname']]));
  }

  /**
   * @param FormAdminEvent $event
   *
   * @return void
   */
  public function configureFormMapper(FormAdminEvent $event): void
  {
    $domain = $event->getObject();
    $domainClass = $event->getModule()->getEntityClass() ?? $domain::class;
    $isRoot = $this->isRoot();
    $isVirtual = $domain instanceof DomainInterface && $domain->getIsVirtual();
    $mapper = $event->getMapper();

    $mapper->addFieldset('devConfig', 'fieldset.dev.config')
      ->setCollapse(true)
      ->setVisible($isRoot)
      ->add(TextField::create('keyname', ['visible' => $isRoot, 'auto_constraints' => false, 'form_options' => ['empty_data' => '']]));

    $mapper->addFieldset('right', false, Fieldset::POSITION_RIGHT)
      ->add(ChoiceField::create('isEnabled', [
        'choices.status.no' => ['value' => false, 'styles' => [
          '--element-choice-current-background:var(--color-main-20)',
          '--element-choice-current-color:var(--color-main-100)',
          '--element-choice-hover-color:var(--color-main-100)',
        ]],
        'choices.status.yes' => ['value' => true, 'styles' => [
          '--element-choice-current-background:var(--color-green-20)',
          '--element-choice-current-color:var(--color-green-100)',
          '--element-choice-hover-color:var(--color-green-100)',
        ]],
      ]));

    $mapper->addFieldset('generalInformation', 'fieldset.generalInformation')
      ->add(self::requiredText('name', ['label' => 'fields.nameDomain.entitled']))
      ->addGroup('domain', false)
        ->add(SelectField::create('scheme', [
          DomainInterface::SCHEME_HTTPS => DomainInterface::SCHEME_HTTPS,
          DomainInterface::SCHEME_HTTP => DomainInterface::SCHEME_HTTP,
        ], [
          'required' => true,
          'size' => Group::SIZE_COL_2,
          'constraints' => [new NotBlank()],
          'setter' => static function (DomainInterface $domain, mixed $value): void {
            if (\is_string($value) && '' !== $value)
            {
              $domain->setScheme($value);
            }
          },
        ]))
        ->add(self::requiredText('domain', ['size' => Group::SIZE_COL_8]))
        ->add(SelectField::create('domainEnv', $this->environments(), [
          'required' => true,
          'size' => Group::SIZE_COL_2,
          'constraints' => [new NotBlank()],
          'setter' => static function (DomainInterface $domain, mixed $value): void {
            if (\is_string($value) && '' !== $value)
            {
              $domain->setDomainEnv($value);
            }
          },
        ]))
      ->end()
      ->addGroup('parameters', false)
        ->setDirection(Group::DIRECTION_COLUMN)
        ->setStyle(Group::STYLE_BOOLEAN)
        ->add(SwitchField::create('isMaster', ['helper' => 'fields.isMaster.information']))
        ->add(SwitchField::create('onePage', ['helper' => 'fields.onePage.information']))
        ->add(SwitchField::create('isVirtual', [
          'helper' => 'fields.isVirtual.information',
          'container_class' => 'view-element-by-choices-language domain-not-language',
          'attr' => self::viewByChoices('.view-element-by-choices', 'domain-virtual', 'domain-not-virtual'),
        ]))
        ->add(SwitchField::create('isTranslate', [
          'helper' => 'fields.isTranslate.information',
          'container_class' => 'view-element-by-choices domain-not-virtual',
          'attr' => self::viewByChoices('.view-element-by-choices-language', 'domain-language', 'domain-not-language'),
        ]))
      ->end()
      ->add(EntityField::create('master', $domainClass, [
        'label' => 'fields.domainMaster.entitled',
        'query_builder' => self::mastersQuery(...),
        'choice_label' => 'name',
        'container_class' => 'view-element-by-choices domain-virtual',
        'required' => $isVirtual,
        // DomainInterface::setMaster() takes a domain: an empty choice keeps the master
        'setter' => static function (DomainInterface $domain, mixed $master): void {
          if ($master instanceof DomainInterface)
          {
            $domain->setMaster($master);
          }
        },
      ]))
      ->add(EntityField::create('masterLanguage', $domainClass, [
        'label' => 'fields.domainMaster.entitled',
        'query_builder' => self::mastersQuery(...),
        'choice_label' => 'name',
        'container_class' => 'view-element-by-choices-language domain-language',
        'getter' => static fn (DomainInterface $domain): ?DomainInterface => $domain->getMaster(),
        'setter' => static function (DomainInterface $domain, mixed $master): void {
          if ($master instanceof DomainInterface && $domain->getIsTranslate())
          {
            $domain->setMaster($master);
          }
        },
      ]))
      ->add(TextField::create('language'));

    $mapper->addFieldset('domainLogo', 'fieldset.domainLogo')
      ->addGroup('logo', false)
        ->setDirection(Group::DIRECTION_COLUMN)
        ->addGroup('logoMaster', false)
          ->add(UploadField::create('logo'))
          ->add(UploadField::create('logoSecond'))
        ->end()
        ->addGroup('logoSecond', false)
          ->add(UploadField::create('favicon'))
          ->add(UploadField::create('logoEmail'))
        ->end()
      ->end();

    $mapper->addFieldset('domainConfig', 'fieldset.domainConfig')
      ->addGroup('robots', 'groups.robots')
        ->setDirection(Group::DIRECTION_COLUMN)
        ->addGroup('robotsSwitches', false)
          ->setStyle(Group::STYLE_BOOLEAN)
          ->add(self::configSwitch('isIndex', 'fields.isIndexDomain.information'))
          ->add(self::configSwitch('isFollow', 'fields.isFollowDomain.information'))
        ->end()
        ->addGroup('seo', false)
          ->add(self::configText('seoTitleSuffixHomepage'))
          ->add(self::configText('seoTitleSuffix'))
        ->end()
      ->end()
      ->addGroup('redirect', 'groups.redirect')
        ->add(TextField::create('redirectUrl'))
        ->add(ChoiceField::create('redirectWithUri', ['choices.status.no' => false, 'choices.status.yes' => true], ['size' => Group::SIZE_COL_5]))
      ->end();
  }

  /**
   * A domain without key gets its name as key (Domain::setKeyname() turns it into a key).
   *
   * @param FormAdminEvent $event
   *
   * @return void
   */
  protected function formSaveBefore(FormAdminEvent $event): void
  {
    $domain = $event->getObject();
    if ($domain instanceof DomainInterface && !$domain->getKeyname())
    {
      $domain->setKeyname($domain->getName());
    }
  }

  /**
   * @return bool
   */
  private function isRoot(): bool
  {
    return $this->authorizationChecker->isGranted('ROLE_ROOT');
  }

  /**
   * @return array<string, string> Environments of the configuration of http-bundle (austral_http.env.list)
   */
  private function environments(): array
  {
    $environments = [];
    $list = $this->httpConfiguration->get('env.list', []);
    foreach (\is_array($list) ? $list : [] as $environment)
    {
      if (\is_string($environment))
      {
        $environments[$environment] = $environment;
      }
    }

    return $environments;
  }

  /**
   * @param EntityRepository<object> $repository
   *
   * @return QueryBuilder The domains that are not virtual, by name
   */
  private static function mastersQuery(EntityRepository $repository): QueryBuilder
  {
    return $repository->createQueryBuilder('root')
      ->where('root.isVirtual = :isVirtual')
      ->setParameter('isVirtual', false)
      ->addOrderBy('root.name', 'ASC');
  }

  /**
   * @param string               $name
   * @param array<string, mixed> $options
   *
   * @return TextField A text that cannot be empty (the setter takes a string)
   */
  private static function requiredText(string $name, array $options = []): TextField
  {
    return TextField::create($name, $options + ['required' => true, 'constraints' => [new NotBlank()], 'form_options' => ['empty_data' => '']]);
  }

  /**
   * A switch saved in the configuration keys of the domain.
   *
   * @param string $key
   * @param string $helper
   *
   * @return SwitchField
   */
  private static function configSwitch(string $key, string $helper): SwitchField
  {
    return SwitchField::create($key, [
      'helper' => $helper,
      'getter' => static fn (DomainInterface $domain): bool => (bool) $domain->getConfigKey($key, false),
      'setter' => static function (DomainInterface $domain, mixed $value) use ($key): void {
        $domain->setConfigKey($key, (bool) $value);
      },
    ]);
  }

  /**
   * A text saved in the configuration keys of the domain.
   *
   * @param string $key
   *
   * @return TextField
   */
  private static function configText(string $key): TextField
  {
    return TextField::create($key, [
      'helper' => "fields.{$key}.information",
      'getter' => static fn (DomainInterface $domain): ?string => \is_string($value = $domain->getConfigKey($key)) ? $value : null,
      'setter' => static function (DomainInterface $domain, mixed $value) use ($key): void {
        $domain->setConfigKey($key, \is_string($value) && '' !== $value ? $value : null);
      },
    ]);
  }

  /**
   * Attributes read by the script of the admin that shows the fields of a choice.
   *
   * @param string $children Selector of the fields shown or hidden
   * @param string $enabled  Class of the fields shown when the switch is on
   * @param string $disabled Class of the fields shown when the switch is off
   *
   * @return array<string, string>
   */
  private static function viewByChoices(string $children, string $enabled, string $disabled): array
  {
    return [
      'data-view-by-choices-parent' => '.form-container',
      'data-view-by-choices-children' => $children,
      'data-view-by-choices' => (string) json_encode(['true' => $enabled, 'false' => $disabled]),
    ];
  }
}
