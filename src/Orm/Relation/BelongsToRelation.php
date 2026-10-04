<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Contract\Database\Orm\RelationInterface;
use Raxos\Contract\Database\Orm\StructureInterface;
use Raxos\Contract\Database\Orm\WritableRelationInterface;
use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Database\Orm\Attribute\BelongsTo;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\ReferenceModelMissingException;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Database\Query\Expression\ColumnRef;
use Raxos\Database\Query\Literal\Literal;
use function assert;

/**
 * Class BelongsToRelation
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 * @implements RelationInterface<TDeclaringModel, TReferenceModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 1.0.17
 */
final readonly class BelongsToRelation implements RelationInterface, WritableRelationInterface
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
     * Allows identity-cache lookup when the relationship targets the related primary key.
     *
     * @var bool
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public bool $referenceKeyIsPrimaryKey;

    /**
     * Resolves related model properties to their database columns.
     *
     * @var StructureInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public StructureInterface $referenceStructure;

    /**
     * BelongsToRelation constructor.
     *
     * @param BelongsTo $attribute
     * @param RelationDefinition $property
     * @param StructureInterface<TDeclaringModel|Model> $declaringStructure
     *
     * @throws OrmExceptionInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public BelongsTo $attribute,
        public RelationDefinition $property,
        public StructureInterface $declaringStructure
    )
    {
        $referenceModel = $this->property->types[0] ?? throw new ReferenceModelMissingException($this->property, $this->declaringStructure);
        $this->referenceStructure = StructureGenerator::for($referenceModel);

        $referencePrimaryKey = $this->referenceStructure->getRelationPrimaryKey();

        $this->declaringKey = RelationHelper::composeKey(
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $referencePrimaryKey->asForeignKeyFor($this->declaringStructure)
        );

        $this->referenceKey = RelationHelper::composeKey(
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $referencePrimaryKey
        );

        $this->referenceKeyIsPrimaryKey = $this->attribute->referenceKey === null;
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

        $cache = $this->referenceStructure->connection->cache;

        $cached = $this->referenceKeyIsPrimaryKey
            ? $cache->get($this->referenceStructure->class, $declaringValue)
            : RelationHelper::findCached($declaringValue, $this->referenceStructure, $this->referenceKey);

        if ($cached !== null) {
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
        [$cached, $uncached] = RelationHelper::partitionModels(
            $this->referenceStructure,
            $instances
                ->column($this->declaringKey->column)
                ->filter(static fn(mixed $value): bool => $value !== null)
                ->unique(),
            $this->referenceKeyIsPrimaryKey ? null : $this->referenceKey
        );

        if ($cached->isNotEmpty()) {
            $this->onBeforeRelations($cached, $instances);
        }

        if ($uncached->isNotEmpty()) {
            $this->referenceStructure->class::select()
                ->whereIn($this->referenceKey, $uncached)
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

        $instance->{$this->declaringKey->column} = $newValue?->{$this->referenceKey->column};
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
            $map[$reference->{$this->referenceKey->column}] = $reference;
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
