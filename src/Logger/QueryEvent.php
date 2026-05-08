<?php
declare(strict_types=1);

namespace Raxos\Database\Logger;

use Raxos\Contract\Database\Query\QueryInterface;
use Raxos\Database\Query\Query;
use Raxos\Foundation\Util\{Stopwatch, StringUtil};
use ReflectionClass;
use ReflectionProperty;
use function htmlspecialchars;
use function str_replace;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

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
            [$modelClassRef, $paramsRef] = self::reflectionProperties();

            $params = $paramsRef->getValue($query);
            $sql = htmlspecialchars($query->toSql(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            foreach ($params as $key => $value) {
                $escapedValue = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $escapedKey = htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $sql = str_replace($escapedKey, "<abbr title=\"{$escapedValue}\">{$escapedKey}</abbr>", $sql);
            }

            $modelClass = $modelClassRef->getValue($query);

            if ($modelClass !== null) {
                $short = htmlspecialchars(StringUtil::shortClassName($modelClass), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $modelClassEscaped = htmlspecialchars($modelClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                $query = "<abbr title=\"{$modelClassEscaped}\">{$short}</abbr>: {$sql}";
            } else {
                $query = $sql;
            }
        }

        return $this->printBase($query, $backtrace);
    }

    /**
     * @return array{0: ReflectionProperty, 1: ReflectionProperty}
     * @author Bas Milius <bas@mili.us>
     * @since 2.3.0
     */
    private static function reflectionProperties(): array
    {
        static $cache = null;

        if ($cache === null) {
            $classRef = new ReflectionClass(Query::class);
            $cache = [
                $classRef->getProperty('modelClass'),
                $classRef->getProperty('params')
            ];
        }

        return $cache;
    }

}
