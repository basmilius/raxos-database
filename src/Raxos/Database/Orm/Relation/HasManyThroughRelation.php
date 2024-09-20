<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Contract\QueryInterface;
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Attribute\HasManyThrough;
use Raxos\Database\Orm\Contract\RelationInterface;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\StructureException;
use Raxos\Database\Orm\Structure\Structure;
use Raxos\Database\Query\Struct\{ColumnLiteral, Select};
use function array_filter;
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
 * @since 1.0.17
 */
final readonly class HasManyThroughRelation implements RelationInterface
{

    public ColumnLiteral $declaringKey;
    public ColumnLiteral $declaringLinkingKey;
    public ColumnLiteral $referenceKey;
    public ColumnLiteral $referenceLinkingKey;

    public Structure $linkingStructure;
    public Structure $referenceStructure;

    /**
     * HasManyThroughRelation constructor.
     *
     * @param HasManyThrough $attribute
     * @param RelationDefinition $property
     * @param Structure $declaringStructure
     *
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public HasManyThrough $attribute,
        public RelationDefinition $property,
        public Structure $declaringStructure
    )
    {
        $this->linkingStructure = Structure::of($this->attribute->linkingModel);
        $this->referenceStructure = Structure::of($this->attribute->referenceModel);

        $declaringPrimaryKey = $this->declaringStructure->getRelationPrimaryKey();
        $linkingPrimaryKey = $this->linkingStructure->getRelationPrimaryKey();

        $this->declaringKey = RelationHelper::composeKey(
            $this->declaringStructure->connection->grammar,
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $declaringPrimaryKey
        );

        $this->declaringLinkingKey = RelationHelper::composeKey(
            $this->linkingStructure->connection->grammar,
            $this->attribute->declaringLinkingKey,
            $this->attribute->declaringLinkingKeyTable,
            $declaringPrimaryKey->asForeignKeyFor($this->linkingStructure)
        );

        $this->referenceLinkingKey = RelationHelper::composeKey(
            $this->linkingStructure->connection->grammar,
            $this->attribute->referenceLinkingKey,
            $this->attribute->referenceLinkingKeyTable,
            $linkingPrimaryKey
        );

        $this->referenceKey = RelationHelper::composeKey(
            $this->referenceStructure->connection->grammar,
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $linkingPrimaryKey->asForeignKeyFor($this->referenceStructure)
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
            ->join($this->linkingStructure->table, fn(QueryInterface $query) => $query
                ->on($this->referenceKey, $this->referenceLinkingKey))
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
        return $this->referenceStructure->class::select(prepared: false)
            ->join($this->linkingStructure->table, fn(QueryInterface $query) => $query
                ->on($this->referenceKey, $this->referenceLinkingKey))
            ->where($this->declaringLinkingKey, $this->declaringKey)
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy));
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
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

        $this->referenceStructure->class::select($select)
            ->join($this->linkingStructure->table, fn(QueryInterface $query) => $query
                ->on($this->referenceKey, $this->referenceLinkingKey))
            ->where($this->declaringLinkingKey, in($values->toArray()))
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy))
            ->withQuery(RelationHelper::onBeforeRelations($instances, $this->onBeforeRelations(...)))
            ->array();
    }

    /**
     * Apply the results to the instances' relation cache.
     *
     * @param Model[] $results
     * @param ModelArrayList<int, Model> $instances
     *
     * @return void
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    private function onBeforeRelations(array $results, ModelArrayList $instances): void
    {
        foreach ($instances as $instance) {
            $result = array_filter($results, fn(Model $reference) => $reference->backbone->data->getValue('__local_linking_key') === $instance->{$this->declaringKey->column});

            $instance->backbone->relationCache->setValue(
                $this->property->name,
                new ModelArrayList($result)
            );
        }
    }

}
