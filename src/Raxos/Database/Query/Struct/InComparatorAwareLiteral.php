<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use JetBrains\PhpStorm\Pure;
use Raxos\Database\Query\QueryBaseInterface;
use function array_map;
use function implode;
use function is_int;

/**
 * Class InComparatorAwareLiteral
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.0
 */
readonly class InComparatorAwareLiteral extends ComparatorAwareLiteral
{

    /**
     * InComparatorAwareLiteral constructor.
     *
     * @param array $options
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    #[Pure]
    public function __construct(
        private array $options
    )
    {
        parent::__construct('');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function get(QueryBaseInterface $query): string
    {
        $options = $this->options;

        $options = array_map(static fn(mixed $option) => $option instanceof Literal || is_int($option) ? $option : $query->connection->quote($option), $options);
        $options = implode(', ', $options);

        return "in({$options})";
    }

}
