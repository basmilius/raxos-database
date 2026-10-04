<?php
declare(strict_types=1);

namespace RaxosTests\Database;

use Raxos\Contract\Database\ConnectionInterface;
use Raxos\Database\Orm\{Model, ModelArrayList};
use Raxos\Database\Orm\Attribute\{BelongsTo, BelongsToMany, BelongsToThrough, Column, HasMany, HasManyThrough, HasOne, HasOneThrough, PrimaryKey, Table};

#[Table('unit_departments')]
final class UnitDepartment extends Model
{

    #[PrimaryKey]
    public int $id;
    #[Column]
    public string $name;
    #[HasMany(UnitTeam::class, referenceKey: 'department_id', orderBy: 'unit_teams.id DESC')]
    public ModelArrayList $teams;
    #[HasOne(referenceKey: 'department_id', orderBy: 'unit_teams.id DESC')]
    public ?UnitTeam $firstTeam;
    #[HasManyThrough(UnitMember::class, UnitTeam::class, referenceKey: 'team_id', declaringLinkingKey: 'department_id', orderBy: 'unit_members.id DESC')]
    public ModelArrayList $members;
    #[HasOneThrough(UnitTeam::class, referenceLinkingKey: 'lead_id', declaringLinkingKey: 'department_id')]
    public ?UnitMember $leader;

}

#[Table('unit_teams')]
final class UnitTeam extends Model
{

    #[PrimaryKey]
    public int $id;
    #[Column]
    public ?int $department_id;
    #[Column]
    public ?int $lead_id;
    #[BelongsTo(declaringKey: 'department_id')]
    public ?UnitDepartment $department;

}

#[Table('unit_members')]
final class UnitMember extends Model
{

    #[PrimaryKey]
    public int $id;
    #[Column]
    public ?int $team_id;
    #[BelongsToThrough(UnitTeam::class, declaringKey: 'team_id', referenceLinkingKey: 'department_id')]
    public ?UnitDepartment $department;
    #[BelongsToMany(UnitTag::class, linkingTable: 'unit_member_tags', declaringLinkingKey: 'member_id', referenceLinkingKey: 'tag_id', orderBy: 'unit_tags.id ASC')]
    public ModelArrayList $tags;

}

#[Table('unit_tags')]
final class UnitTag extends Model
{

    #[PrimaryKey]
    public int $id;
    #[Column]
    public string $name;

}

function unitRelations(): ConnectionInterface
{
    $connection = unitConnection();
    $connection->execute('CREATE TABLE unit_departments (id INTEGER PRIMARY KEY, name TEXT)');
    $connection->execute('CREATE TABLE unit_teams (id INTEGER PRIMARY KEY, department_id INTEGER NULL, lead_id INTEGER NULL)');
    $connection->execute('CREATE TABLE unit_members (id INTEGER PRIMARY KEY, team_id INTEGER NULL)');
    $connection->execute('CREATE TABLE unit_tags (id INTEGER PRIMARY KEY, name TEXT)');
    $connection->execute('CREATE TABLE unit_member_tags (member_id INTEGER, tag_id INTEGER)');
    $connection->execute("INSERT INTO unit_departments VALUES (1,'first'),(2,'empty'),(3,'last')");
    $connection->execute('INSERT INTO unit_teams VALUES (10,1,100),(11,1,101),(20,3,200)');
    $connection->execute('INSERT INTO unit_members VALUES (100,10),(101,11),(102,10),(200,20),(300,NULL)');
    $connection->execute("INSERT INTO unit_tags VALUES (0,'zero'),(2,'second')");
    $connection->execute('INSERT INTO unit_member_tags VALUES (100,2),(100,0),(101,2)');

    return $connection;
}
