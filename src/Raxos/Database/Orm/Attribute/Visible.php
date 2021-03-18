<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class Visible
 *
 * @author Bas Milius <bas@glybe.nl>
 * @package Raxos\Database\Orm\Attribute
 * @since 2.0.0
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Visible
{
}
