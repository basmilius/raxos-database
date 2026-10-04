<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Contract\Database\Orm\RelationInterface;
use Raxos\Contract\Database\Orm\StructureInterface;
use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Database\Orm\Attribute\HasMany;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Database\Query\Expression\ColumnRef;
use Raxos\Database\Query\Literal\Literal;

/**
 * Class HasManyRelation
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 * @implements RelationInterface<TDeclaringModel, TReferenceModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.17
 */
final readonly class HasManyRelation implements RelationInterface
{
    /**
     * Identifies the owner property used to match related rows.
     *
     * @var ColumnRef
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public ColumnRef $declaringKey;

    /**
     * Identifies the related property used to match the owner or linking row.
     *
     * @var ColumnRef
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public ColumnRef $referenceKey;

    /**
     * Resolves related model properties to their database columns.
     *
     * @var StructureInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public StructureInterface $referenceStructure;

    /**
     * HasManyRelation constructor.
     *
     * @param HasMany $attribute
     * @param RelationDefinition $property
     * @param StructureInterface<TDeclaringModel> $declaringStructure
     *
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public HasMany $attribute,
        public RelationDefinition $property,
        public StructureInterface $declaringStructure
    )
    {
        $this->referenceStructure = StructureGenerator::for($this->attribute->referenceModel);

        $declaringPrimaryKey = $this->declaringStructure->getRelationPrimaryKey();

        $this->declaringKey = RelationHelper::composeKey(
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $declaringPrimaryKey
        );

        $this->referenceKey = RelationHelper::composeKey(
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $declaringPrimaryKey->asForeignKeyFor($this->referenceStructure)
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
        return $this->referenceStructure->class::where($this->referenceKey, $instance->backbone->getValue($this->declaringKey->column) ?? Literal::of('NULL'))
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy))
            ->conditional($this->attribute->withDeleted, static fn(QueryInterface $query) => $query
                ->withDeleted());
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function rawQuery(): QueryInterface
    {
        return $this->referenceStructure->class::select()
            ->where($this->referenceKey, $this->declaringKey)
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy))
            ->conditional($this->attribute->withDeleted, static fn(QueryInterface $query) => $query
                ->withDeleted());
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
            ->filter(static fn(mixed $value): bool => $value !== null)
            ->unique();

        if ($values->isEmpty()) {
            return;
        }

        $this->referenceStructure->class::select()
            ->whereIn($this->referenceKey, $values)
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy))
            ->conditional($this->attribute->withDeleted, static fn(QueryInterface $query) => $query
                ->withDeleted())
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
    private function onBeforeRelations(
        ArrayListInterface $results,
        ArrayListInterface $instances
    ): void
    {
        $map = [];

        foreach ($results as $reference) {
            $map[$reference->{$this->referenceKey->column}][] = $reference;
        }

        foreach ($instances as $instance) {
            if ($instance->backbone->relationCache->hasValue($this->property->name)) {
                continue;
            }

            $matched = $map[$instance->backbone->getValue($this->declaringKey->column)] ?? [];

            $instance->backbone->relationCache->setValue(
                $this->property->name,
                new ModelArrayList($matched)
            );
        }
    }
}
