<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Contract\{QueryInterface, StructureInterface};
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Attribute\BelongsToMany;
use Raxos\Database\Orm\Contract\RelationInterface;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\StructureException;
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Database\Query\Select;
use Raxos\Database\Query\Literal\ColumnLiteral;
use Raxos\Foundation\Contract\ArrayListInterface;
use function implode;
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
 * @since 1.0.17
 */
final readonly class BelongsToManyRelation implements RelationInterface
{

    public ColumnLiteral $declaringKey;
    public ColumnLiteral $declaringLinkingKey;
    public ColumnLiteral $referenceKey;
    public ColumnLiteral $referenceLinkingKey;

    public StructureInterface $referenceStructure;

    /**
     * BelongsToManyRelation constructor.
     *
     * @param BelongsToMany $attribute
     * @param RelationDefinition $property
     * @param StructureInterface<TDeclaringModel> $declaringStructure
     *
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public BelongsToMany $attribute,
        public RelationDefinition $property,
        public StructureInterface $declaringStructure
    )
    {
        $this->referenceStructure = StructureGenerator::for($this->attribute->referenceModel);

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

        $this->declaringKey = RelationHelper::composeKey(
            $this->declaringStructure->connection->grammar,
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $declaringPrimaryKey
        );

        $this->declaringLinkingKey = RelationHelper::composeKey(
            $this->declaringStructure->connection->grammar,
            $this->attribute->declaringLinkingKey,
            $this->attribute->declaringLinkingKeyTable,
            $declaringPrimaryKey->asForeignKeyForTable($linkingTable)
        );

        $this->referenceLinkingKey = RelationHelper::composeKey(
            $this->referenceStructure->connection->grammar,
            $this->attribute->referenceLinkingKey,
            $this->attribute->referenceLinkingKeyTable,
            $referencePrimaryKey->asForeignKeyForTable($linkingTable)
        );

        $this->referenceKey = RelationHelper::composeKey(
            $this->referenceStructure->connection->grammar,
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $referencePrimaryKey
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function fetch(Model $instance): Model|ModelArrayList|null
    {
        return $this
            ->query($instance)
            ->arrayList();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
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
     * @since 1.0.17
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
     * @since 1.0.17
     */
    public function eagerLoad(ArrayListInterface $instances): void
    {
        $values = $instances
            ->filter(fn(Model $instance) => !$instance->backbone->relationCache->hasValue($this->property->name))
            ->column($this->declaringKey->column)
            ->unique();

        if ($values->isEmpty()) {
            return;
        }

        $select = new Select()->add(
            $this->referenceStructure->class::col('*'),
            __local_linking_key: $this->declaringLinkingKey
        );

        $this->referenceStructure->class::select($select)
            ->join($this->declaringLinkingKey->table, fn(QueryInterface $query) => $query
                ->on($this->referenceLinkingKey, $this->referenceKey))
            ->whereIn($this->declaringLinkingKey, $values)
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy))
            ->withQuery(RelationHelper::onBeforeRelations($instances, $this->onBeforeRelations(...)))
            ->array();
    }

    /**
     * Apply the results to the instances' relation cache.
     *
     * @param ArrayListInterface<int, TReferenceModel> $results
     * @param ArrayListInterface<int, TDeclaringModel> $instances
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    private function onBeforeRelations(ArrayListInterface $results, ArrayListInterface $instances): void
    {
        foreach ($instances as $instance) {
            $result = $results
                ->filter(fn(Model $reference) => $reference->backbone->data->getValue('__local_linking_key') === $instance->{$this->declaringKey->column})
                ->values();

            $instance->backbone->relationCache->setValue(
                $this->property->name,
                $result->convertTo(ModelArrayList::class)
            );
        }
    }

}
