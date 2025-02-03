<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Contract\QueryInterface;
use Raxos\Foundation\Contract\ArrayableInterface;
use Traversable;
use function array_map;
use function implode;
use function is_int;
use function iterator_to_array;

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
     * @param iterable $options
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function __construct(
        private iterable $options
    )
    {
        parent::__construct('');
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function get(QueryInterface $query): string
    {
        $options = $this->options;

        if ($options instanceof ArrayableInterface) {
            $options = $options->toArray();
        } elseif ($options instanceof Traversable) {
            $options = iterator_to_array($options);
        }

        $options = array_map(static fn(mixed $option) => $option instanceof Literal || is_int($option) ? $option : $query->connection->quote($option), $options);
        $options = implode(', ', $options);

        return "in({$options})";
    }

}
