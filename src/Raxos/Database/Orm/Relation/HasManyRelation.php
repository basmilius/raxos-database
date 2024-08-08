<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Orm\{InternalHelper, InternalModelData, Model, ModelArrayList};
use Raxos\Database\Orm\Attribute\HasMany;
use Raxos\Database\Orm\Definition\ColumnDefinition;
use Raxos\Database\Query\QueryInterface;
use Raxos\Database\Query\Struct\ColumnLiteral;
use Raxos\Foundation\Collection\ArrayList;
use function Raxos\Database\Query\in;

/**
 * Class HasManyRelation
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 * @implements RelationInterface<TDeclaringModel, TReferenceModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.16
 */
final readonly class HasManyRelation implements RelationInterface
{

    /** @var class-string<TReferenceModel>|class-string<Model> */
    public string $referenceModel;

    public ColumnLiteral $referenceKey;
    public ColumnLiteral $declaringKey;

    /**
     * HasManyRelation constructor.
     *
     * @param HasMany $attribute
     * @param ColumnDefinition $column
     * @param class-string<TDeclaringModel>|class-string<Model> $declaringModel
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function __construct(
        public HasMany $attribute,
        public ColumnDefinition $column,
        public string $declaringModel
    )
    {
        $this->referenceModel = $this->attribute->referenceModel;
        InternalModelData::initialize($this->referenceModel);

        $dialect = $this->referenceModel::dialect();
        $declaringPrimaryKey = InternalHelper::getRelationPrimaryKey($this->declaringModel);

        $this->referenceKey = InternalHelper::composeRelationKey(
            $dialect,
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $declaringPrimaryKey->asForeignKeyFor($this->referenceModel)
        );

        $this->declaringKey = InternalHelper::composeRelationKey(
            $dialect,
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $declaringPrimaryKey
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function fetch(Model $instance): Model|ModelArrayList|null
    {
        $cache = $instance::cache();
        $relationCache = InternalHelper::getRelationCache($this);

        $relationCache[$instance->__master] ??= $this
            ->query($instance)
            ->arrayList()
            ->map(InternalHelper::getRelationCacheHelper($cache));

        return $relationCache[$instance->__master];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function query(Model $instance): QueryInterface
    {
        return $this->referenceModel::where($this->referenceKey, $instance->{$this->declaringKey->column})
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function rawQuery(): QueryInterface
    {
        return $this->referenceModel::query(false)
            ->where($this->referenceKey, $this->declaringKey)
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function eagerLoad(ArrayList $instances): void
    {
        $relationCache = InternalHelper::getRelationCache($this);

        $values = $instances
            ->filter(fn(Model $instance) => !isset($relationCache[$instance->__master]))
            ->column($this->declaringKey->column)
            ->unique();

        if ($values->isEmpty()) {
            return;
        }

        $results = $this->referenceModel::select()
            ->where($this->referenceKey, in($values->toArray()))
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy))
            ->arrayList();

        foreach ($instances as $instance) {
            $relationCache[$instance->__master] = $results->filter(fn(Model $reference) => $reference->{$this->referenceKey->column} === $instance->{$this->declaringKey->column});
        }
    }

}
