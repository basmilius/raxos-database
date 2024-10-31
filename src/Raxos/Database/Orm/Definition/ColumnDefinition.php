<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Definition;

use BackedEnum;
use JetBrains\PhpStorm\{ArrayShape, Pure};
use Raxos\Database\Orm\Contract\CasterInterface;
use Raxos\Foundation\Util\ArrayUtil;

/**
 * Class ColumnDefinition
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Definition
 * @since 1.0.17
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
     * @param class-string<BackedEnum>|null $enumClass
     * @param bool $isForeignKey
     * @param bool $isPrimaryKey
     * @param bool $isComputed
     * @param bool $isImmutable
     * @param string $key
     * @param bool $nullable
     * @param string[] $types
     * @param string[]|null $visibleOnly
     * @param string $name
     * @param string|null $alias
     * @param bool $isHidden
     * @param bool $isVisible
     *
     * @author Bas Milius <bas@mili.us>
     * @since 31-10-2024
     */
    public function __construct(
        public ?string $caster,
        public mixed $defaultValue,
        public ?string $enumClass,
        public bool $isForeignKey,
        public bool $isPrimaryKey,
        public bool $isComputed,
        public bool $isImmutable,
        public string $key,
        public bool $nullable,
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
     * @since 1.0.17
     */
    #[Pure]
    public function isIn(array $keys): bool
    {
        return ArrayUtil::in($keys, [$this->name, $this->alias, $this->key]);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    #[ArrayShape([
        'name' => 'string',
        'alias' => 'string|null',
        'is_hidden' => 'bool',
        'is_visible' => 'bool',
        'caster' => 'string|null',
        'default_value' => 'mixed',
        'is_foreign_key' => 'bool',
        'is_primary_key' => 'bool',
        'is_computed' => 'bool',
        'is_immutable' => 'bool',
        'key' => 'string',
        'types' => 'string[]',
        'visible_only' => 'string[]'
    ])]
    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'caster' => $this->caster,
            'default_value' => $this->defaultValue,
            'is_foreign_key' => $this->isForeignKey,
            'is_primary_key' => $this->isPrimaryKey,
            'is_computed' => $this->isComputed,
            'is_immutable' => $this->isImmutable,
            'key' => $this->key,
            'types' => $this->types,
            'visible_only' => $this->visibleOnly
        ];
    }

}
