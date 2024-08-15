<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

use JetBrains\PhpStorm\Pure;
use Raxos\Database\Orm\Caster\CasterInterface;
use Raxos\Foundation\Util\ArrayUtil;

/**
 * Class ColumnDefinition
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Definition
 * @since 13-08-2024
 */
final readonly class ColumnDefinition extends PropertyDefinition
{

    /**
     * ColumnDefinition constructor.
     *
     * @template TCaster of CasterInterface
     *
     * @param class-string<TCaster>|null $caster
     * @param mixed $defaultValue
     * @param bool $isForeignKey
     * @param bool $isPrimaryKey
     * @param bool $isComputed
     * @param bool $isImmutable
     * @param string[] $types
     * @param array|null $visibleOnly
     * @param string $key
     * @param string $name
     * @param string|null $alias
     * @param bool $isHidden
     * @param bool $isVisible
     *
     * @author Bas Milius <bas@mili.us>
     * @since 13-08-2024
     */
    public function __construct(
        public ?string $caster,
        public mixed $defaultValue,
        public bool $isForeignKey,
        public bool $isPrimaryKey,
        public bool $isComputed,
        public bool $isImmutable,
        public string $key,
        public array $types,
        public ?array $visibleOnly,
        string $name,
        ?string $alias,
        bool $isHidden = false,
        bool $isVisible = false
    )
    {
        parent::__construct($name, $alias, $isHidden, $isVisible);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    #[Pure]
    public function isIn(array $keys): bool
    {
        return ArrayUtil::in($keys, [$this->name, $this->alias, $this->key]);
    }

}
