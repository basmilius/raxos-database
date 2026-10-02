<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\Orm\{OrmExceptionInterface, RelationInterface, StructureInterface};
use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Database\Orm\Attribute\HasOneThrough;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\ReferenceModelMissingException;
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Database\Query\Expression\ColumnRef;
use Raxos\Database\Query\Literal\Literal;
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

    public ColumnRef $declaringKey;
    public ColumnRef $declaringLinkingKey;
    public ColumnRef $referenceKey;
    public ColumnRef $referenceLinkingKey;

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
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $declaringPrimaryKey
        );

        $this->declaringLinkingKey = RelationHelper::composeKey(
            $this->attribute->declaringLinkingKey,
            $this->attribute->declaringLinkingKeyTable,
            $declaringPrimaryKey->asForeignKeyFor($this->linkingStructure)
        );

        $this->referenceLinkingKey = RelationHelper::composeKey(
            $this->attribute->referenceLinkingKey,
            $this->attribute->referenceLinkingKeyTable,
            $referencePrimaryKey->asForeignKeyFor($this->linkingStructure)
        );

        $this->referenceKey = RelationHelper::composeKey(
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
     * @since 3.2.0
     */
    public function query(Model $instance): QueryInterface
    {
        return $this->referenceStructure->class::select()
            ->join($this->linkingStructure->table, fn(QueryInterface $query) => $query
                ->on($this->referenceLinkingKey, $this->referenceKey))
            ->where($this->declaringLinkingKey, $instance->backbone->getValue($this->declaringKey->column) ?? Literal::of('NULL'))
            ->conditional($this->attribute->withDeleted, static fn(QueryInterface $query) => $query
                ->withDeleted());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function rawQuery(): QueryInterface
    {
        return $this->referenceStructure->class::select()
            ->join($this->linkingStructure->table, fn(QueryInterface $query) => $query
                ->on($this->referenceLinkingKey, $this->referenceKey))
            ->where($this->declaringLinkingKey, $this->declaringKey)
            ->conditional($this->attribute->withDeleted, static fn(QueryInterface $query) => $query
                ->withDeleted());
    }

    /**
     * {@inheritdoc}
     *
     * @author Bas Milius <bas@mili.us>
     * @since 3.2.0
     */
    public function eagerLoad(ArrayListInterface $instances): void
    {
        $values = $instances
            ->filter(fn(Model $instance) => !$instance->backbone->relationCache->hasValue($this->property->name))
            ->column($this->declaringKey->column)
            ->filter(static fn(mixed $value): bool => $value !== null)
            ->unique();

        if ($values->isEmpty()) {
            return;
        }

        $pairs = $this->linkingStructure->class::select()
            ->withoutModel()
            ->whereIn($this->declaringLinkingKey, $values)
            ->array();

        $referenceKeyValues = array_column($pairs, $this->referenceLinkingKey->column)
                |> (static fn(array $values): array => array_filter($values, static fn(mixed $value): bool => $value !== null))
                |> array_unique(...)
                |> array_values(...);

        $referenceModels = !empty($referenceKeyValues)
            ? $this->referenceStructure->class::select()
                ->whereIn($this->referenceKey, $referenceKeyValues)
                ->conditional($this->attribute->withDeleted, static fn(QueryInterface $query) => $query
                    ->withDeleted())
                ->array()
            : [];

        $referenceMap = [];

        foreach ($referenceModels as $reference) {
            $referenceMap[$reference->{$this->referenceKey->column}] = $reference;
        }

        $declaringMap = [];

        foreach ($pairs as $pair) {
            $key = $pair[$this->referenceLinkingKey->column];
            $declaringMap[$pair[$this->declaringLinkingKey->column]] = $key !== null ? ($referenceMap[$key] ?? null) : null;
        }

        foreach ($instances as $instance) {
            $result = ($key = $instance->backbone->getValue($this->declaringKey->column)) !== null ? ($declaringMap[$key] ?? null) : null;

            if ($result === null && $instance->backbone->relationCache->hasValue($this->property->name)) {
                continue;
            }

            $instance->backbone->relationCache->setValue($this->property->name, $result);
        }
    }

}
