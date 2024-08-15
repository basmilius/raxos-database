<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Attribute\BelongsToMany;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\StructureException;
use Raxos\Database\Orm\Structure\{Structure, StructureHelper};
use Raxos\Database\Query\QueryInterface;
use Raxos\Database\Query\Struct\{ColumnLiteral, Select};
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
 * @since 15-08-2024
 */
final readonly class BelongsToManyRelation implements RelationInterface
{

    public ColumnLiteral $declaringKey;
    public ColumnLiteral $declaringLinkingKey;
    public ColumnLiteral $referenceKey;
    public ColumnLiteral $referenceLinkingKey;

    public Structure $referenceStructure;

    /**
     * BelongsToManyRelation constructor.
     *
     * @param BelongsToMany $attribute
     * @param RelationDefinition $property
     * @param Structure $declaringStructure
     *
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public function __construct(
        public BelongsToMany $attribute,
        public RelationDefinition $property,
        public Structure $declaringStructure
    )
    {
        $this->referenceStructure = Structure::of($this->attribute->referenceModel);

        $linkingTable = $this->attribute->linkingTable ?? (function (): string {
            $tables = [
                $this->declaringStructure->table,
                $this->referenceStructure->table
            ];

            sort($tables);

            return implode('_', $tables);
        })();

        $declaringPrimaryKey = $this->declaringStructure->getRelationPrimaryKey();
        $referencePrimaryKey = $this->referenceStructure->getRelationPrimaryKey();

        $this->declaringKey = StructureHelper::composeRelationKey(
            $this->declaringStructure->connection->dialect,
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $declaringPrimaryKey
        );

        $this->declaringLinkingKey = StructureHelper::composeRelationKey(
            $this->declaringStructure->connection->dialect,
            $this->attribute->declaringLinkingKey,
            $this->attribute->declaringLinkingKeyTable,
            $declaringPrimaryKey->asForeignKeyForTable($linkingTable)
        );

        $this->referenceLinkingKey = StructureHelper::composeRelationKey(
            $this->referenceStructure->connection->dialect,
            $this->attribute->referenceLinkingKey,
            $this->attribute->referenceLinkingKeyTable,
            $referencePrimaryKey->asForeignKeyForTable($linkingTable)
        );

        $this->referenceKey = StructureHelper::composeRelationKey(
            $this->referenceStructure->connection->dialect,
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $referencePrimaryKey
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public function fetch(Model $instance): Model|ModelArrayList|null
    {
        $relationCache = $instance->backbone->relationCache;

        if ($relationCache->hasValue($this->property->name)) {
            return $relationCache->getValue($this->property->name);
        }

        $result = $this
            ->query($instance)
            ->arrayList();

        $relationCache->setValue($this->property->name, $result);

        return $result;
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public function query(Model $instance): QueryInterface
    {
        return $this->referenceStructure->class::select()
            ->join($this->declaringLinkingKey->table, fn(QueryInterface $query) => $query
                ->on($this->referenceLinkingKey, $this->referenceKey))
            ->where($this->declaringLinkingKey, $instance->{$this->declaringKey->column})
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public function rawQuery(): QueryInterface
    {
        return $this->referenceStructure->class::select()
            ->join($this->declaringLinkingKey->table, fn(QueryInterface $query) => $query
                ->on($this->referenceLinkingKey, $this->referenceKey))
            ->where($this->declaringLinkingKey, $this->declaringKey)
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public function eagerLoad(ModelArrayList $instances): void
    {
        $values = $instances
            ->filter(fn(Model $instance) => !$instance->backbone->relationCache->hasValue($this->property->name))
            ->column($this->declaringKey->column)
            ->unique();

        if ($values->isEmpty()) {
            return;
        }

        $select = Select::new()->add(
            $this->referenceStructure->class::col('*'),
            __local_linking_key: $this->declaringLinkingKey
        );

        $results = $this->referenceStructure->class::select($select)
            ->join($this->declaringLinkingKey->table, fn(QueryInterface $query) => $query
                ->on($this->referenceLinkingKey, $this->referenceKey))
            ->where($this->declaringLinkingKey, in($values->toArray()))
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy))
            ->arrayList();

        foreach ($instances as $instance) {
            $instance->backbone->relationCache->setValue(
                $this->property->name,
                $results->filter(fn(Model $reference) => $reference->getValue('__local_linking_key') === $instance->{$this->declaringKey->column})
            );
        }
    }

}
