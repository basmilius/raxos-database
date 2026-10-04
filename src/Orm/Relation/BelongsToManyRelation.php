<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Relation;

use Raxos\Contract\Collection\ArrayListInterface;
use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Contract\Database\Orm\RelationInterface;
use Raxos\Contract\Database\Orm\StructureInterface;
use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Database\Orm\Attribute\BelongsToMany;
use Raxos\Database\Orm\Definition\RelationDefinition;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\ModelArrayList;
use Raxos\Database\Orm\Structure\StructureGenerator;
use Raxos\Database\Query\Expression\ColumnRef;
use Raxos\Database\Query\Literal\Literal;
use function array_column;
use function array_filter;
use function array_unique;
use function array_values;
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
    /**
     * Identifies the owner property used to match related rows.
     *
     * @var ColumnRef
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public ColumnRef $declaringKey;

    /**
     * Identifies the linking-table column that matches the owner key.
     *
     * @var ColumnRef
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public ColumnRef $declaringLinkingKey;

    /**
     * Identifies the related property used to match the owner or linking row.
     *
     * @var ColumnRef
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public ColumnRef $referenceKey;

    /**
     * Identifies the linking-table column that matches the related key.
     *
     * @var ColumnRef
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public ColumnRef $referenceLinkingKey;

    /**
     * Resolves related model properties to their database columns.
     *
     * @var StructureInterface
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public StructureInterface $referenceStructure;

    /**
     * BelongsToManyRelation constructor.
     *
     * @param BelongsToMany $attribute
     * @param RelationDefinition $property
     * @param StructureInterface<TDeclaringModel> $declaringStructure
     *
     * @throws OrmExceptionInterface
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
            $this->attribute->declaringKey,
            $this->attribute->declaringKeyTable,
            $declaringPrimaryKey
        );

        $this->declaringLinkingKey = RelationHelper::composeKey(
            $this->attribute->declaringLinkingKey,
            $this->attribute->declaringLinkingKeyTable,
            $declaringPrimaryKey->asForeignKeyForTable($linkingTable)
        );

        $this->referenceLinkingKey = RelationHelper::composeKey(
            $this->attribute->referenceLinkingKey,
            $this->attribute->referenceLinkingKeyTable,
            $referencePrimaryKey->asForeignKeyForTable($linkingTable)
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
            ->where($this->declaringLinkingKey, $instance->backbone->getValue($this->declaringKey->column) ?? Literal::of('NULL'))
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
            ->join($this->declaringLinkingKey->table, fn(QueryInterface $query) => $query
                ->on($this->referenceLinkingKey, $this->referenceKey))
            ->where($this->declaringLinkingKey, $this->declaringKey)
            ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                ->orderBy($this->attribute->orderBy))
            ->conditional($this->attribute->withDeleted, static fn(QueryInterface $query) => $query
                ->withDeleted());
    }

    /**
     * {@inheritdoc}
     *
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

        $pivotSelect = [$this->declaringLinkingKey, $this->referenceLinkingKey];

        $pairs = $this->declaringStructure->connection->query()
            ->select($pivotSelect)
            ->from($this->declaringLinkingKey->table)
            ->whereIn($this->declaringLinkingKey, $values)
            ->array();

        $referenceKeyValues = array_column($pairs, $this->referenceLinkingKey->column)
                |> (static fn(array $values): array => array_filter($values, static fn(mixed $value): bool => $value !== null))
                |> array_unique(...)
                |> array_values(...);

        $referenceModels = !empty($referenceKeyValues)
            ? $this->referenceStructure->class::select()
                ->whereIn($this->referenceKey, $referenceKeyValues)
                ->conditional($this->attribute->orderBy !== null, fn(QueryInterface $query) => $query
                    ->orderBy($this->attribute->orderBy))
                ->conditional($this->attribute->withDeleted, static fn(QueryInterface $query) => $query
                    ->withDeleted())
                ->array()
            : [];

        $referenceMap = [];
        $referenceOrder = [];

        foreach ($referenceModels as $position => $reference) {
            $referenceOrder[$reference->{$this->referenceKey->column}] = $position;
            $referenceMap[$reference->{$this->referenceKey->column}] = $reference;
        }

        $declaringMap = [];

        foreach ($pairs as $pair) {
            $declaringKeyValue = $pair[$this->declaringLinkingKey->column];
            $referenceKeyValue = $pair[$this->referenceLinkingKey->column];

            if (isset($referenceMap[$referenceKeyValue])) {
                $declaringMap[$declaringKeyValue][] = $referenceMap[$referenceKeyValue];
            }
        }

        if ($this->attribute->orderBy !== null) {
            foreach ($declaringMap as &$references) {
                usort($references, fn(Model $left, Model $right): int => $referenceOrder[$left->{$this->referenceKey->column}] <=> $referenceOrder[$right->{$this->referenceKey->column}]);
            }
            unset($references);
        }

        foreach ($instances as $instance) {
            if ($instance->backbone->relationCache->hasValue($this->property->name)) {
                continue;
            }

            $instance->backbone->relationCache->setValue(
                $this->property->name,
                new ModelArrayList($declaringMap[$instance->backbone->getValue($this->declaringKey->column)] ?? [])
            );
        }
    }
}
