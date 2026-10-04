<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Contract\Database\Orm\RelationInterface;
use Raxos\Contract\Database\Orm\StructureInterface;
use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Database\Orm\Attribute\BelongsToThrough;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\ReferenceModelMissingException;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Database\Query\Expression\ColumnRef;
use Raxos\Database\Query\Literal\Literal;
use function array_column;
use function array_filter;
use function array_unique;
use function array_values;

/**
 * Class BelongsToThroughRelation
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
final readonly class BelongsToThroughRelation implements RelationInterface
{
    /**
     * Identifies the owner property used to match related rows.
     *
     * @var ColumnRef
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public ColumnRef $declaringKey;

    /**
     * Identifies the linking-table column that matches the owner key.
     *
     * @var ColumnRef
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public ColumnRef $declaringLinkingKey;

    /**
     * Identifies the related property used to match the owner or linking row.
     *
     * @var ColumnRef
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public ColumnRef $referenceKey;

    /**
     * Identifies the linking-table column that matches the related key.
     *
     * @var ColumnRef
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public ColumnRef $referenceLinkingKey;

    /**
     * Resolves linking-model properties to their database columns.
     *
     * @var StructureInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public StructureInterface $linkingStructure;

    /**
     * Resolves related model properties to their database columns.
     *
     * @var StructureInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public StructureInterface $referenceStructure;

    /**
     * BelongsToThroughRelation constructor.
     *
     * @param BelongsToThrough $attribute
     * @param RelationDefinition $property
     * @param StructureInterface<TDeclaringModel> $declaringStructure
     *
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public function __construct(
        public BelongsToThrough $attribute,
        public RelationDefinition $property,
        public StructureInterface $declaringStructure
    )
    {
        $referenceModel = $this->property->types[0] ?? throw new ReferenceModelMissingException($this->property, $this->declaringStructure);

        $this->linkingStructure = StructureGenerator::for($this->attribute->linkingModel);
        $this->referenceStructure = StructureGenerator::for($referenceModel);

        $linkingPrimaryKey = $this->linkingStructure->getRelationPrimaryKey();
        $referencePrimaryKey = $this->referenceStructure->getRelationPrimaryKey();

        $this->declaringKey = RelationHelper::composeKey(
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $linkingPrimaryKey->asForeignKeyFor($this->declaringStructure)
        );

        $this->declaringLinkingKey = RelationHelper::composeKey(
            $this->attribute->declaringLinkingKey,
            $this->attribute->declaringLinkingKeyTable,
            $linkingPrimaryKey
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
     * @since 1.1.0
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
     * @since 1.1.0
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
