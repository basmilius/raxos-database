<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Orm\{InternalHelper, InternalStructure, Model, ModelArrayList};
use Raxos\Database\Orm\Attribute\BelongsToMany;
use Raxos\Database\Orm\Definition\ColumnDefinition;
use Raxos\Database\Query\QueryInterface;
use Raxos\Database\Query\Struct\ColumnLiteral;
use Raxos\Foundation\Collection\ArrayList;
use function implode;
use function Raxos\Database\Query\in;
use function sort;

/**
 * Class BelongsToManyRelation
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 * @implements RelationInterface<TDeclaringModel, TReferenceModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.16
 */
final readonly class BelongsToManyRelation implements RelationInterface
{

    private const string LOCAL_LINKING_KEY = '__local_linking_key';

    /** @var class-string<TReferenceModel>|class-string<Model> */
    public string $referenceModel;

    public ColumnLiteral $referenceKey;
    public ColumnLiteral $referenceLinkingKey;
    public ColumnLiteral $declaringKey;
    public ColumnLiteral $declaringLinkingKey;

    /**
     * BelongsToManyRelation constructor.
     *
     * @param BelongsToMany $attribute
     * @param ColumnDefinition $column
     * @param class-string<TDeclaringModel>|class-string<Model> $declaringModel
     *
     * @throws DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function __construct(
        public BelongsToMany $attribute,
        public ColumnDefinition $column,
        public string $declaringModel
    )
    {
        $this->referenceModel = $this->attribute->referenceModel;
        InternalStructure::initialize($this->referenceModel);

        $linkingTable = $this->attribute->linkingTable ?? (function () {
            $tables = [
                $this->declaringModel::table(),
                $this->referenceModel::table(),
            ];

            sort($tables);

            return implode('_', $tables);
        })();

        $dialect = $this->referenceModel::dialect();
        $declaringPrimaryKey = InternalHelper::getRelationPrimaryKey($this->declaringModel);
        $referencePrimaryKey = InternalHelper::getRelationPrimaryKey($this->referenceModel);

        $this->referenceKey = InternalHelper::composeRelationKey(
            $dialect,
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $referencePrimaryKey->asForeignKeyForTable($linkingTable)
        );

        $this->referenceLinkingKey = InternalHelper::composeRelationKey(
            $dialect,
            $this->attribute->referenceLinkingKey,
            $this->attribute->referenceLinkingKeyTable,
            $referencePrimaryKey
        );

        $this->declaringLinkingKey = InternalHelper::composeRelationKey(
            $dialect,
            $this->attribute->declaringLinkingKey,
            $this->attribute->declaringLinkingKeyTable,
            $declaringPrimaryKey->asForeignKeyForTable($linkingTable)
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

        $relationCache[$instance->backbone] ??= $this
            ->query($instance)
            ->arrayList()
            ->map(InternalHelper::getRelationCacheHelper($cache));

        return $relationCache[$instance->backbone];
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function query(Model $instance): QueryInterface
    {
        return $this->referenceModel::select()
            ->join($this->declaringLinkingKey->table, fn(QueryInterface $query) => $query
                ->on($this->referenceLinkingKey, $this->referenceKey))
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
            ->join($this->declaringLinkingKey->table, fn(QueryInterface $query) => $query
                ->on($this->referenceLinkingKey, $this->referenceKey))
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
            ->filter(fn(Model $instance) => !isset($relationCache[$instance->backbone]))
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
            ->join($this->declaringLinkingKey->table, fn(QueryInterface $query) => $query
                ->on($this->referenceLinkingKey, $this->referenceKey))
            ->where($this->declaringLinkingKey, in($values->toArray()))
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy))
            ->arrayList();

        foreach ($instances as $instance) {
            $relationCache[$instance->backbone] = $results->filter(fn(Model $reference) => $reference->__data[self::LOCAL_LINKING_KEY] === $instance->{$this->declaringKey->column});
        }
    }

}
