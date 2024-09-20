<?php
declare(strict_types=1);

namespace Raxos\Database\Query\Struct;

use Raxos\Database\Contract\{QueryInterface, QueryValueInterface};
use function is_numeric;

/**
 * Class Select
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Query\Struct
 * @since 1.0.17
 */
final readonly class Select
{

    public bool $isEmpty;

    /**
     * Select constructor.
     *
     * @param Entry[] $entries
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public array $entries = []
    )
    {
        $this->isEmpty = empty($this->entries);
    }

    /**
     * Adds the given value(s).
     *
     * @param QueryInterface|QueryValueInterface|string|int ...$values
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function add(QueryInterface|QueryValueInterface|string|int ...$values): self
    {
        $entries = $this->entries;

        foreach ($values as $key => $value) {
            $alias = is_numeric($key) ? null : $key;
            $entries[] = new Entry($value, $alias);
        }

        return new self($entries);
    }

    /**
     * Creates a new select set.
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function new(): self
    {
        return new self();
    }

    /**
     * Creates a new select set for the given keys.
     *
     * @param array $keys
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public static function of(array $keys): self
    {
        $entries = [];

        foreach ($keys as $key => $value) {
            $alias = is_numeric($key) ? null : $key;
            $entries[] = new Entry($value, $alias);
        }

        return new self($entries);
    }

}
