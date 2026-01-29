<?php
declare(strict_types=1);

namespace Raxos\Database\Logger;

use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Database\Query\Query;
use Raxos\Foundation\Util\{Stopwatch, StringUtil};
use ReflectionClass;
use function str_replace;

/**
 * Class QueryEvent
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Logger
 * @since 1.0.16
 */
final readonly class QueryEvent extends Event
{

    /**
     * QueryEvent constructor.
     *
     * @param QueryInterface|string $query
     * @param Stopwatch $stopwatch
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function __construct(
        public QueryInterface|string $query,
        Stopwatch $stopwatch
    )
    {
        parent::__construct($stopwatch);
    }

    /**
     * {@inheritdoc}
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.16
     */
    public function print(bool $backtrace): string
    {
        $query = $this->query;

        if ($query instanceof QueryInterface) {
            $classRef = new ReflectionClass(Query::class);
            $modelClassRef = $classRef->getProperty('modelClass');
            $paramsRef = $classRef->getProperty('params');

            $params = $paramsRef->getValue($query);
            $sql = $query->toSql();

            foreach ($params as $key => $value) {
                $sql = str_replace($key, "<abbr title='{$value}'>{$key}</abbr>", $sql);
            }

            $modelClass = $modelClassRef->getValue($query);

            if ($modelClass !== null) {
                $short = StringUtil::shortClassName($modelClass);

                $query = "<abbr title='{$modelClass}'>{$short}</abbr>: {$sql}";
            } else {
                $query = $sql;
            }
        }

        return $this->printBase($query, $backtrace);
    }

}
