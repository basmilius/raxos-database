<?php
declare(strict_types=1);

namespace RaxosTests\Database;

use Raxos\Contract\Database\ConnectionInterface;
use Raxos\Database\Orm\Attribute\{Alias, Caster, Column, Computed, ConnectionId, Embeddable, Embedded, Hidden, Immutable, Macro, Polymorphic, PrimaryKey, Table, Visible};
use Raxos\Database\Orm\Caster\JsonCaster;
use Raxos\Database\Orm\Model;
use Raxos\Database\Query\Expr;
use stdClass;

enum UnitAccountState: string
{

    case Active = 'active';
    case Disabled = 'disabled';

}

#[Embeddable]
final class UnitAddress
{

    #[Column]
    public ?string $city = null;
    #[Column, Alias('postal')]
    public ?string $zip = null;

}

final class UnitAccountMacros
{

    public static int $calls = 0;

    public static function label(UnitAccount $account): string
    {
        ++self::$calls;

        return strtoupper($account->name);
    }

}

#[Table('unit_accounts')]
class UnitAccount extends Model
{

    #[PrimaryKey]
    public int $id;
    #[Column('display_name'), Alias('label')]
    public string $name;
    #[Column, Hidden]
    public string $secret;
    #[Column]
    public bool $enabled = true;
    #[Column, Caster(JsonCaster::class)]
    public ?array $settings;
    #[Column]
    public ?UnitAccountState $state;
    #[Column, Immutable]
    public string $fixed;
    #[Embedded('home_')]
    public ?UnitAddress $address;
    #[Macro(UnitAccountMacros::label(...)), Visible]
    public string $display;
    #[Column, Computed]
    public int $doubled;

    public static function getQueryableColumns(array $columns): array
    {
        return [...$columns, self::col('*'), 'doubled' => Expr::mul(self::col('id'), 2)];
    }

}

#[Table('unit_accounts'), ConnectionId('unit-secondary')]
class UnitNamedBase extends Model
{

    #[PrimaryKey]
    public int $id;

}

final class UnitNamedChild extends UnitNamedBase {}

#[Table('unit_accounts')]
final class UnitSecretView extends Model
{

    #[PrimaryKey]
    public int $id;
    #[Column('secret')]
    public string $name;

}

final class UnitMissingTable extends Model
{

    #[PrimaryKey]
    public int $id;

}

#[Table('unit_accounts')]
final class UnitInvalidCaster extends Model
{

    #[Column, Caster(stdClass::class)]
    public string $data;

}

#[Table('unit_accounts')]
final class UnitInvalidEmbedded extends Model
{

    #[Embedded]
    public stdClass $address;

}

#[Table('unit_accounts')]
final class UnitNoPrimaryKey extends Model
{

    #[Column('display_name')]
    public string $name;

}

#[Table('unit_animals'), Polymorphic(column: 'kind', map: ['cat' => UnitCat::class])]
class UnitAnimal extends Model
{

    #[PrimaryKey]
    public int $id;
    #[Column]
    public string $kind;

}

final class UnitCat extends UnitAnimal
{

    #[Column]
    public string $name;

}

function unitAccounts(): ConnectionInterface
{
    $connection = unitOrm();
    $connection->execute('CREATE TABLE unit_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, display_name TEXT, secret TEXT, enabled INTEGER, settings TEXT NULL, state TEXT NULL, fixed TEXT, home_city TEXT NULL, home_zip TEXT NULL)');
    $connection->execute("INSERT INTO unit_accounts VALUES (1,'Bas','secret',0,'{\"count\":0}','active','fixed','Amsterdam','1000'),(2,'Other','other-secret',1,NULL,NULL,'locked',NULL,NULL)");
    $connection->execute('CREATE TABLE unit_animals (id INTEGER PRIMARY KEY, kind TEXT, name TEXT)');
    $connection->execute("INSERT INTO unit_animals VALUES (1,'cat','Milo')");
    UnitAccountMacros::$calls = 0;

    return $connection;
}
