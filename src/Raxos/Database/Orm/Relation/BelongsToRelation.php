<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Attribute\BelongsTo;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Error\{RelationException, StructureException};
use Raxos\Database\Orm\Structure\{Structure, StructureHelper};
use Raxos\Database\Query\QueryInterface;
use Raxos\Database\Query\Struct\ColumnLiteral;
use function assert;
use function is_numeric;
use function Raxos\Database\Query\in;
use function sprintf;

/**
 * Class BelongsToRelation
 *
 * @template TDeclaringModel of Model
 * @template TReferenceModel of Model
 * @implements RelationInterface<TDeclaringModel, TReferenceModel>
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Relation
 * @since 15-08-2024
 */
final readonly class BelongsToRelation implements RelationInterface, WritableRelationInterface
{

    public ColumnLiteral $declaringKey;
    public ColumnLiteral $referenceKey;

    public Structure $referenceStructure;

    /**
     * BelongsToRelation constructor.
     *
     * @param BelongsTo $attribute
     * @param RelationDefinition $property
     * @param Structure<TDeclaringModel|Model> $declaringStructure
     *
     * @throws RelationException
     * @throws StructureException
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public function __construct(
        public BelongsTo $attribute,
        public RelationDefinition $property,
        public Structure $declaringStructure
    )
    {
        $referenceModel = $this->property->types[0] ?? throw new RelationException(sprintf('Could not find reference model of relation "%s" of model "%s".', $this->property->name, $this->declaringStructure->class), RelationException::ERR_REFERENCE_MODEL_MISSING);
        $this->referenceStructure = Structure::of($referenceModel);

        $referencePrimaryKey = $this->referenceStructure->getRelationPrimaryKey();

        $this->declaringKey = StructureHelper::composeRelationKey(
            $this->declaringStructure->connection->dialect,
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $referencePrimaryKey
        );

        $this->referenceKey = StructureHelper::composeRelationKey(
            $this->referenceStructure->connection->dialect,
            $this->attribute->referenceKey,
            $this->attribute->referenceKeyTable,
            $referencePrimaryKey->asForeignKeyFor($this->declaringStructure)
        );
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public function fetch(Model $instance): Model|ModelArrayList|null
    {
        $foreignKey = $instance->{$this->referenceKey->column};

        if ($foreignKey === null || (is_numeric($foreignKey) && (int)$foreignKey === 0)) {
            return null;
        }

        $cache = $this->referenceStructure->connection->cache;

        if (($cached = $cache->find($this->referenceStructure->class, fn(Model $model) => $model->{$this->declaringKey->column} === $foreignKey)) !== null) {
            return $cached;
        }

        return $this
            ->query($instance)
            ->single();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public function query(Model $instance): QueryInterface
    {
        return $this->referenceStructure->class::where($this->declaringKey, $instance->{$this->referenceKey->column});
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public function rawQuery(): QueryInterface
    {
        return $this->referenceStructure->class::select(prepared: false)
            ->where($this->declaringKey, $this->referenceKey);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public function eagerLoad(ModelArrayList $instances): void
    {
        $cache = $this->referenceStructure->connection->cache;

        $values = $instances
            ->column($this->referenceKey->column)
            ->unique()
            ->filter(fn(string|int|null $key) => $key !== null && !$cache->has($this->referenceStructure->class, $key));

        if ($values->isEmpty()) {
            return;
        }

        $this->referenceStructure->class::select()
            ->where($this->declaringKey, in($values->toArray()))
            ->array();
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 15-08-2024
     */
    public function write(Model $instance, RelationDefinition $property, Model|ModelArrayList|null $newValue): void
    {
        assert($newValue === null || $newValue instanceof $this->referenceStructure->class);

        $instance->{$this->declaringKey->column} = $newValue?->{$this->referenceKey->column};
    }

}
