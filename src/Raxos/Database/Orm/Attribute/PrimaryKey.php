<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class PrimaryKey
 *
 * Defines the primary key of a model.
 *
 *  <code>
 *      class Post extends Model {
 *          #[PrimaryKey]
 *          public string $id;
 *      }
 *  </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.16
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class PrimaryKey extends Column {}
