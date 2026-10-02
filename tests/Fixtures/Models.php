<?php
declare(strict_types=1);

namespace RaxosTests\Database;

use Raxos\Database\Orm\Attribute\BelongsTo;
use Raxos\Database\Orm\Attribute\Column;
use Raxos\Database\Orm\Attribute\HasMany;
use Raxos\Database\Orm\Attribute\HasOne;
use Raxos\Database\Orm\Attribute\PrimaryKey;
use Raxos\Database\Orm\Attribute\SoftDelete;
use Raxos\Database\Orm\Attribute\Table;
use Raxos\Database\Orm\Model;
use Raxos\Database\Orm\ModelArrayList;
use function Raxos\Database\Query\literal;

#[Table('parents')]
final class ParentModel extends Model
{

    #[PrimaryKey]
    public int $id;

    #[Column]
    public int $external_key;

    #[Column]
    public string $name;

    #[HasMany(ChildModel::class, referenceKey: 'parent_id', declaringKey: 'id')]
    public ModelArrayList $children;

    #[HasOne(referenceKey: 'parent_id', declaringKey: 'id')]
    public ?ChildModel $child;
}

#[Table('children')]
final class ChildModel extends Model
{

    #[PrimaryKey]
    public int $id;

    #[Column]
    public int $parent_id;

    #[Column]
    public int $parent_key;

    #[BelongsTo(referenceKey: 'external_key', declaringKey: 'parent_key')]
    public ?ParentModel $parent;
}

#[Table('soft_items'), SoftDelete('deleted_at')]
final class SoftModel extends Model
{

    #[PrimaryKey]
    public int $id;

    #[Column]
    public int $group_id;

    #[Column]
    public ?string $deleted_at;
}

final class SpecializedModelArrayList extends ModelArrayList {}

#[Table('raxos_test_counts'), SoftDelete('deleted_at')]
final class CountedModel extends Model
{

    #[PrimaryKey]
    public int $id;

    #[Column]
    public int $group_id;

    #[Column]
    public int $quantity;

    #[Column]
    public ?string $deleted_at;

    public static function getQueryableColumns(array $columns): array
    {
        return [...$columns, self::col('*'), 'quantity' => literal('greatest(`raxos_test_counts`.`quantity`, 2)')];
    }
}
