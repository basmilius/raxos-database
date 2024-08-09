<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Orm\{InternalHelper, InternalModelData, Model, ModelArrayList};
use Raxos\Database\Orm\Attribute\HasManyThrough;
use Raxos\Database\Orm\Definition\ColumnDefinition;
use Raxos\Database\Query\QueryInterface;
use Raxos\Database\Query\Struct\ColumnLiteral;
use Raxos\Foundation\Collection\ArrayList;
use function Raxos\Database\Query\in;

/**
 * Class HasManyThroughRelation
 *
 * @template TDeclaringModel of Model
 * @template TLinkingModel of Model
 * @template TReferenceModel of Model
 * @implements RelationInterface<TDeclaringModel, TReferenceModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.16
 */
final readonly class HasManyThroughRelation implements RelationInterface
{

    private const string LOCAL_LINKING_KEY = '__local_linking_key';

    /** @var class-string<TReferenceModel>|class-string<Model> */
    public string $referenceModel;

    /** @var class-string<TLinkingModel>|class-string<Model> */
    public string $linkingModel;

    public ColumnLiteral $referenceKey;
    public ColumnLiteral $referenceLinkingKey;
    public ColumnLiteral $declaringKey;
    public ColumnLiteral $declaringLinkingKey;

    /**
     * HasManyThroughRelation constructor.
     *
     * @param HasManyThrough $attribute
     * @param ColumnDefinition $column
     * @param class-string<TDeclaringModel>|class-string<Model> $declaringModel
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function __construct(
        public HasManyThrough $attribute,
        public ColumnDefinition $column,
        public string $declaringModel
    )
    {
        $this->referenceModel = $this->attribute->referenceModel;
        $this->linkingModel = $this->attribute->linkingModel;
        InternalModelData::initialize($this->referenceModel);
        InternalModelData::initialize($this->linkingModel);

        $dialect = $this->referenceModel::dialect();
        $declaringPrimaryKey = InternalHelper::getRelationPrimaryKey($this->declaringModel);
        $linkingPrimaryKey = InternalHelper::getRelationPrimaryKey($this->linkingModel);

        $this->referenceKey = InternalHelper::composeRelationKey(
            $dialect,
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $linkingPrimaryKey->asForeignKeyFor($this->referenceModel)
        );

        $this->referenceLinkingKey = InternalHelper::composeRelationKey(
            $dialect,
            $this->attribute->referenceLinkingKey,
            $this->attribute->referenceLinkingKeyTable,
            $linkingPrimaryKey
        );

        $this->declaringLinkingKey = InternalHelper::composeRelationKey(
            $dialect,
            $this->attribute->declaringLinkingKey,
            $this->attribute->declaringLinkingKeyTable,
            $declaringPrimaryKey->asForeignKeyFor($this->linkingModel)
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
        return $this->referenceModel::select()
            ->join($this->linkingModel::table(), fn(QueryInterface $query) => $query
                ->on($this->referenceKey, $this->referenceLinkingKey))
            ->where($this->declaringLinkingKey, $instance->{$this->declaringKey->column})
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
        return $this->referenceModel::select()
            ->join($this->linkingModel::table(), fn(QueryInterface $query) => $query
                ->on($this->referenceKey, $this->referenceLinkingKey))
            ->where($this->declaringLinkingKey, $this->declaringKey)
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

        $select = [
            $this->referenceModel::column('*') => true,
            self::LOCAL_LINKING_KEY => $this->declaringLinkingKey
        ];

        $results = $this->referenceModel::select($select)
            ->join($this->linkingModel::table(), fn(QueryInterface $query) => $query
                ->on($this->referenceKey, $this->referenceLinkingKey))
            ->where($this->declaringLinkingKey, in($values->toArray()))
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy))
            ->arrayList();

        foreach ($instances as $instance) {
            $relationCache[$instance->__master] = $results->filter(fn(Model $reference) => $reference->__data[self::LOCAL_LINKING_KEY] === $instance->{$this->declaringKey->column});
        }
    }

}
