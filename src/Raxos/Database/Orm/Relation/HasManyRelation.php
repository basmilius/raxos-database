<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Attribute\HasMany;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\StructureException;
use Raxos\Database\Orm\Structure\{Structure, StructureHelper};
use Raxos\Database\Query\QueryInterface;
use Raxos\Database\Query\Struct\ColumnLiteral;
use function Raxos\Database\Query\in;

/**
 * Class HasManyRelation
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 * @implements RelationInterface<TDeclaringModel, TReferenceModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 15-08-2024
 */
final readonly class HasManyRelation implements RelationInterface
{

    public ColumnLiteral $declaringKey;
    public ColumnLiteral $referenceKey;

    public Structure $referenceStructure;

    /**
     * HasManyRelation constructor.
     *
     * @param HasMany $attribute
     * @param RelationDefinition $property
     * @param Structure<TDeclaringModel|Model> $declaringStructure
     *
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public function __construct(
        public HasMany $attribute,
        public RelationDefinition $property,
        public Structure $declaringStructure
    )
    {
        $this->referenceStructure = Structure::of($this->attribute->referenceModel);

        $declaringPrimaryKey = $this->declaringStructure->getRelationPrimaryKey();

        $this->declaringKey = StructureHelper::composeRelationKey(
            $this->declaringStructure->connection->dialect,
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $declaringPrimaryKey
        );

        $this->referenceKey = StructureHelper::composeRelationKey(
            $this->referenceStructure->connection->dialect,
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $declaringPrimaryKey->asForeignKeyFor($this->referenceStructure)
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
        return $this->referenceStructure->class::where($this->referenceKey, $instance->{$this->declaringKey->column})
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
        return $this->referenceStructure->class::select(prepared: false)
            ->where($this->referenceKey, $this->declaringKey)
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

        $results = $this->referenceStructure->class::select()
            ->where($this->referenceKey, in($values->toArray()))
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy))
            ->arrayList();

        foreach ($instances as $instance) {
            $instance->backbone->relationCache->setValue(
                $this->property->name,
                $results->filter(fn(Model $reference) => $reference->{$this->referenceKey->column} === $instance->{$this->declaringKey->column})
            );
        }
    }

}
