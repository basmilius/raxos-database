<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class Computed
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 13-08-2024
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Computed implements AttributeInterface {}
