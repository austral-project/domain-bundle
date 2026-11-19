<?php

/*
 * This file is part of the Austral Domain Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Austral\DomainBundle\Attribute;

use Austral\EntityBundle\Attribute\AustralEntityAttribute;

/**
 * Class Translate
 *
 * @Attribute
 *
 * @Target("CLASS")
 *
 * @author  Matthieu Beurel <matthieu@austral.dev>
 *
 * @final
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class DomainFilter extends AustralEntityAttribute
{
  /**
   * @param bool $autoDomainId
   * @param bool $forAllDomainEnabled
   * @param bool $autoAttachement
   */
  public function __construct(
    public bool $autoDomainId = false,
    public bool $forAllDomainEnabled = false,
    public bool $autoAttachement = true
  ) {
  }
}
