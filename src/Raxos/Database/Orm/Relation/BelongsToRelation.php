<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Orm\{InternalHelper, InternalModelData, Model};
use Raxos\Database\Orm\Attribute\BelongsTo;
use Raxos\Database\Orm\Definition\ColumnDefinition;
use Raxos\Database\Query\QueryInterface;
use Raxos\Database\Query\Struct\ColumnLiteral;
use Raxos\Foundation\Collection\ArrayList;
use function assert;
use function is_numeric;
use function Raxos\Database\Query\in;

/**
 * Class BelongsToRelation
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 * @implements RelationInterface<TDeclaringModel, TReferenceModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.16
 */
final readonly class BelongsToRelation implements RelationInterface, WritableRelationInterface
{

    /** @var class-string<TReferenceModel>|class-string<Model> */
    public string $referenceModel;
    public ColumnLiteral $referenceKey;
    public ColumnLiteral $declaringKey;

    /**
     * BelongsToRelation constructor.
     *
     * @param BelongsTo $attribute
     * @param ColumnDefinition $column
     * @param class-string<TDeclaringModel>|class-string<Model> $declaringModel
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function __construct(
        public BelongsTo $attribute,
        public ColumnDefinition $column,
        public string $declaringModel
    )
    {
        $this->referenceModel = $this->column->types[0] ?? 0;
        InternalModelData::initialize($this->referenceModel);

        $dialect = $this->referenceModel::dialect();
        $referencePrimaryKey = InternalHelper::getRelationPrimaryKey($this->referenceModel);

        $this->referenceKey = InternalHelper::composeRelationKey(
            $dialect,
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $referencePrimaryKey->asForeignKeyFor($this->declaringModel)
        );

        $this->declaringKey = InternalHelper::composeRelationKey(
            $dialect,
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $referencePrimaryKey
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function fetch(Model $instance): ?Model
    {
        $primaryKey = $instance->{$this->referenceKey->column};

        if ($primaryKey === null || (is_numeric($primaryKey) && (int)$primaryKey === 0)) {
            return null;
        }

        $cache = $instance::cache();

        if ($cache->has($this->referenceModel, $primaryKey)) {
            return $cache->get($this->referenceModel, $primaryKey);
        }

        return $this
            ->query($instance)
            ->single();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function query(Model $instance): QueryInterface
    {
        return $this->referenceModel::where($this->declaringKey, $instance->{$this->referenceKey->column});
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function rawQuery(): QueryInterface
    {
        return $this->referenceModel::query(false)
            ->where($this->declaringKey, $this->referenceKey);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function eagerLoad(ArrayList $instances): void
    {
        $cache = $this->referenceModel::cache();

        $values = $instances
            ->column($this->referenceKey->column)
            ->unique()
            ->filter(fn(string|int $key) => !$cache->has($this->referenceModel, $key));

        if ($values->isEmpty()) {
            return;
        }

        $this->referenceModel::select()
            ->where($this->declaringKey, in($values->toArray()))
            ->arrayList();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function write(Model $instance, ColumnDefinition $column, mixed $newValue): void
    {
        assert($newValue === null || $newValue instanceof $this->referenceModel);

        $instance->{$this->referenceKey->column} = $newValue?->id;
    }

}
