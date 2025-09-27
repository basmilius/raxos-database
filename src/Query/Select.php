<?php
declare(strict_types=1);

namespace Raxos\Database\Query;

use Raxos\Contract\Database\Query\{QueryExpressionInterface, QueryInterface, QueryLiteralInterface};
use Stringable;
use function is_numeric;

/**
 * Class Select
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query
 * @since 1.5.0
 */
final readonly class Select
{

    public bool $isEmpty;

    /**
     * Select constructor.
     *
     * @param SelectEntry[] $entries
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function __construct(
        public array $entries = []
    )
    {
        $this->isEmpty = empty($this->entries);
    }

    /**
     * Adds values to the select.
     *
     * @param QueryInterface|QueryExpressionInterface|QueryLiteralInterface|Stringable|string|int|float|bool ...$values
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public function add(QueryInterface|QueryExpressionInterface|QueryLiteralInterface|Stringable|string|int|float|bool ...$values): self
    {
        $entries = [];

        foreach ($values as $key => $value) {
            $alias = !is_numeric($key) ? $key : null;
            $entries[] = new SelectEntry($value, $alias);
        }

        return new self([
            ...$this->entries,
            ...$entries
        ]);
    }

    /**
     * Creates a new select set for the given keys.
     *
     * @param array<QueryInterface|QueryExpressionInterface|QueryLiteralInterface|Stringable|string|int|float|bool> ...$values
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.5.0
     */
    public static function of(array $values): self
    {
        return new self()->add(...$values);
    }

}
