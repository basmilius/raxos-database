<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;
use Raxos\Database\Orm\Contract\AttributeInterface;

/**
 * Class ConnectionId
 *
 * Defines the default connection id the model should use.
 *
 * ```
 * #[ConnectionId('crm')]
 * class Person extends Model {}
 * ```
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 1.0.17
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ConnectionId implements AttributeInterface
{

    /**
     * ConnectionId constructor.
     *
     * @param string $connectionId
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public string $connectionId = 'default'
    ) {}

}
