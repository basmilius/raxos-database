<a href="https://bas.dev">
    <img src="https://bmcdn.nl/assets/branding/logo.svg" alt="Bas Milius" height="48" />
</a>

---

# Raxos Database

A PDO query builder and an ORM configured with PHP attributes for SQLite, MySQL and MariaDB.

[Documentation](https://raxos.dev/database/) | [Packagist](https://packagist.org/packages/raxos/database) | [Raxos](https://github.com/basmilius/raxos)

- Prepared queries, expressions, transactions and query logging.
- Models with casts, views, soft deletes, computed values and relationship attributes.
- Eager loading, SQL counts, pagination and batched model cursors.

## Installation

Requires PHP 8.5 or later. Enable the `pdo` PHP extension. Composer checks the remaining package and extension dependencies declared in [composer.json](composer.json).

```sh
composer require "raxos/database:^3.2"
```

## Usage

```php
<?php
declare(strict_types=1);

use Raxos\Database\Connection\SQLite;
use Raxos\Database\Db;
use Raxos\Database\Orm\Attribute\Column;
use Raxos\Database\Orm\Attribute\PrimaryKey;
use Raxos\Database\Orm\Attribute\Table;
use Raxos\Database\Orm\Model;

require __DIR__ . '/vendor/autoload.php';

#[Table('products')]
final class Product extends Model
{
    #[PrimaryKey]
    public int $id;

    #[Column]
    public string $name;
}

Db::register(SQLite::createFromInMemory());
Db::execute('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

Db::query()->insertIntoValues('products', ['id' => 42, 'name' => 'Keyboard'])->run();

foreach (Product::select()->orderBy('id')->cursor(batchSize: 100) as $product) {
    echo $product->name . PHP_EOL;
}
```

Enable `pdo_sqlite` for this example. Use `Connection\MySql` or `Connection\MariaDb` with a matching PDO driver for those servers.

Model cursors hydrate 100 items per batch by default and release identities introduced by each batch. Pass `retainCache: true` when those identities must remain available afterward. Previously cached models are retained.

## Documentation

- [Connections](https://raxos.dev/database/connections)
- [Query builder](https://raxos.dev/database/query-builder)
- [Models](https://raxos.dev/database/orm/models)
- [Relations](https://raxos.dev/database/orm/relations)
- [Transactions, caching and logging](https://raxos.dev/database/transactions-and-logging)

## Testing

Run this library's Pest suite from the Raxos workspace:

```sh
git clone --recurse-submodules https://github.com/basmilius/raxos.git
cd raxos
composer install
vendor/bin/pest --testsuite=database
```

See [Testing Raxos](https://github.com/basmilius/raxos/blob/main/TESTING.md) for PHP extensions, integration services and coverage commands. The library's [Tests workflow](.github/workflows/tests.yml) also runs in GitHub Actions.

## License

[MIT](LICENSE). Copyright (c) 2017 - present Bas Milius.
