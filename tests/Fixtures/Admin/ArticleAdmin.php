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

namespace Austral\DomainBundle\Tests\Fixtures\Admin;

use Austral\AdminBundle\Admin\Admin;
use Austral\AdminBundle\Event\FormAdminEvent;
use Austral\AdminBundle\Event\ListAdminEvent;
use Austral\FormBundle\Field\TextField;
use Austral\ListBundle\Column\ValueColumn;

/**
 * Admin of the test module "articles".
 */
final class ArticleAdmin extends Admin
{
  public function configureListMapper(ListAdminEvent $event): void
  {
    $event->getMapper()->add(new ValueColumn('title'));
  }

  public function configureFormMapper(FormAdminEvent $event): void
  {
    $event->getMapper()->add(TextField::create('title', ['required' => true, 'form_options' => ['empty_data' => '']]));
  }
}
