<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Collection\ArrayList;
use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Contract\Database\Orm\RelationInterface;
use Raxos\Contract\Database\Orm\StructureInterface;
use Raxos\Contract\Database\Orm\WritableRelationInterface;
use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Database\Orm\Attribute\HasOne;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\ReferenceModelMissingException;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Database\Query\Expression\ColumnRef;
use Raxos\Database\Query\Literal\Literal;
use function assert;

/**
 * Class HasOneRelation
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 * @implements RelationInterface<TDeclaringModel, TReferenceModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.17
 */
final readonly class HasOneRelation implements RelationInterface, WritableRelationInterface
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
     * HasOneRelation constructor.
     *
     * @param HasOne $attribute
     * @param RelationDefinition $property
     * @param StructureInterface<TDeclaringModel|Model> $declaringStructure
     *
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public HasOne $attribute,
        public RelationDefinition $property,
        public StructureInterface $declaringStructure
    )
    {
        $referenceModel = $this->property->types[0] ?? throw new ReferenceModelMissingException($this->property, $this->declaringStructure);
        $this->referenceStructure = StructureGenerator::for($referenceModel);

        $declaringPrimaryKey = $this->declaringStructure->getRelationPrimaryKey();

        $this->referenceKey = RelationHelper::composeKey(
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $declaringPrimaryKey->asForeignKeyFor($this->referenceStructure),
        );

        $this->declaringKey = RelationHelper::composeKey(
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $declaringPrimaryKey
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function fetch(Model $instance): Model|ModelArrayList|null
    {
        $declaringValue = RelationHelper::declaringKeyValue($instance, $this->declaringKey);

        if ($declaringValue === null) {
            return null;
        }

        $cached = RelationHelper::findCached(
            $declaringValue,
            $this->referenceStructure,
            $this->referenceKey
        );

        if ($cached !== null && $this->attribute->orderBy === null) {
            return $cached;
        }

        return $this
            ->query($instance)
            ->single();
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
            ->filter(fn(Model $instance): bool => !$instance->backbone->relationCache->hasValue($this->property->name))
            ->column($this->declaringKey->column)
            ->filter(static fn(mixed $value): bool => $value !== null)
            ->unique();

        [$cached, $uncached] = $this->attribute->orderBy === null
            ? RelationHelper::partitionModels($this->referenceStructure, $values, $this->referenceKey)
            : [new ArrayList(), $values];

        if ($cached->isNotEmpty()) {
            $this->onBeforeRelations($cached, $instances);
        }

        if ($uncached->isNotEmpty()) {
            $this->referenceStructure->class::select()
                ->whereIn($this->referenceKey, $uncached)
                ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                    ->orderBy($this->attribute->orderBy))
                ->conditional($this->attribute->withDeleted, static fn(QueryInterface $query) => $query
                    ->withDeleted())
                ->withQuery(RelationHelper::onBeforeRelations($instances, $this->onBeforeRelations(...)))
                ->array();
        }
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function write(
        Model $instance,
        RelationDefinition $property,
        Model|ModelArrayList|null $newValue
    ): void
    {
        assert($newValue === null || $newValue instanceof $this->referenceStructure->class);

        // note(Bas): remove the relation between the previous value and the instance.
        if (!$instance->backbone->isNew) {
            $oldValue = $instance->{$this->property->name};

            if ($oldValue instanceof Model) {
                $instance->backbone->addSaveTask(function () use ($oldValue): void {
                    $oldValue->{$this->referenceKey->column} = null;
                    $oldValue->save();
                });
            }
        }

        // note(Bas): create a relation between the new value and the instance.
        if ($newValue instanceof Model) {
            $instance->backbone->addSaveTask(function () use ($instance, $newValue): void {
                $newValue->{$this->referenceKey->column} = $instance->backbone->getValue($this->declaringKey->column);
                $newValue->save();
            });
        }
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
            $map[$reference->{$this->referenceKey->column}] ??= $reference;
        }

        foreach ($instances as $instance) {
            $result = ($key = $instance->backbone->getValue($this->declaringKey->column)) !== null ? ($map[$key] ?? null) : null;

            if ($result === null && $instance->backbone->relationCache->hasValue($this->property->name)) {
                continue;
            }

            $instance->backbone->relationCache->setValue(
                $this->property->name,
                $result
            );
        }
    }
}
