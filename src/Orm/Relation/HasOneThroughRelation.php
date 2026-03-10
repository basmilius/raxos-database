<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\Orm\{OrmExceptionInterface, RelationInterface, StructureInterface};
use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Database\Orm\{Error\ReferenceModelMissingException, Model, ModelArrayList};
use Raxos\Database\Orm\Attribute\HasOneThrough;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Database\Query\Literal\ColumnLiteral;
use function array_column;
use function array_filter;
use function array_unique;
use function array_values;

/**
 * Class HasOneThroughRelation
 *
 * @template TDeclaringModel of Model
 * @template TLinkingModel of Model
 * @template TReferenceModel of Model
 * @implements RelationInterface<TDeclaringModel, TReferenceModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.1.0
 */
final readonly class HasOneThroughRelation implements RelationInterface
{

    public ColumnLiteral $declaringKey;
    public ColumnLiteral $declaringLinkingKey;
    public ColumnLiteral $referenceKey;
    public ColumnLiteral $referenceLinkingKey;

    public StructureInterface $linkingStructure;
    public StructureInterface $referenceStructure;

    /**
     * HasOneThroughRelation constructor.
     *
     * @param HasOneThrough $attribute
     * @param RelationDefinition $property
     * @param StructureInterface $declaringStructure
     *
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function __construct(
        public HasOneThrough $attribute,
        public RelationDefinition $property,
        public StructureInterface $declaringStructure
    )
    {
        $referenceModel = $this->property->types[0] ?? throw new ReferenceModelMissingException($this->property, $this->declaringStructure);

        $this->linkingStructure = StructureGenerator::for($this->attribute->linkingModel);
        $this->referenceStructure = StructureGenerator::for($referenceModel);

        $declaringPrimaryKey = $this->declaringStructure->getRelationPrimaryKey();
        $referencePrimaryKey = $this->referenceStructure->getRelationPrimaryKey();

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
            $referencePrimaryKey->asForeignKeyFor($this->linkingStructure)
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
     * @since 1.1.0
     */
    public function fetch(Model $instance): Model|ModelArrayList|null
    {
        return $this
            ->query($instance)
            ->single();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function query(Model $instance): QueryInterface
    {
        return $this->referenceStructure->class::select()
            ->join($this->linkingStructure->table, fn(QueryInterface $query) => $query
                ->on($this->referenceLinkingKey, $this->referenceKey))
            ->where($this->declaringLinkingKey, $instance->{$this->declaringKey->column});
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function rawQuery(): QueryInterface
    {
        return $this->referenceStructure->class::select(prepared: false)
            ->join($this->linkingStructure->table, fn(QueryInterface $query) => $query
                ->on($this->referenceLinkingKey, $this->referenceKey))
            ->where($this->declaringLinkingKey, $this->declaringKey);
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
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

        $pairs = $this->linkingStructure->class::select()
            ->withoutModel()
            ->whereIn($this->declaringLinkingKey, $values)
            ->array();

        $referenceKeyValues = array_column($pairs, $this->referenceLinkingKey->column)
                |> array_filter(...)
                |> array_unique(...)
                |> array_values(...);

        $referenceModels = !empty($referenceKeyValues)
            ? $this->referenceStructure->class::select()
                ->whereIn($this->referenceKey, $referenceKeyValues)
                ->array()
            : [];

        $referenceMap = [];

        foreach ($referenceModels as $reference) {
            $referenceMap[$reference->{$this->referenceKey->column}] = $reference;
        }

        $declaringMap = [];

        foreach ($pairs as $pair) {
            $declaringMap[$pair[$this->declaringLinkingKey->column]] = $referenceMap[$pair[$this->referenceLinkingKey->column]] ?? null;
        }

        foreach ($instances as $instance) {
            $result = $declaringMap[$instance->{$this->declaringKey->column}] ?? null;

            if ($result === null && $instance->backbone->relationCache->hasValue($this->property->name)) {
                continue;
            }

            $instance->backbone->relationCache->setValue($this->property->name, $result);
        }
    }

}
