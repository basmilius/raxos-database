<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Attribute;

use Attribute;

/**
 * Class ConnectionId
 *
 * Defines the default connection id the model should use.
 *
 * <code>
 *     #[ConnectionId('crm')]
 *     class Person extends Model {}
 * </code>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Attribute
 * @since 13-08-2024
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
     * @since 13-08-2024
     */
    public function __construct(
        public string $connectionId = 'default'
    ) {}

}
