<?php
declare(strict_types=1);

namespace Raxos\Database\Dialect;

use Raxos\Database\Error\DatabaseException;
use Raxos\Database\Error\RuntimeException;
use function array_map;
use function explode;
use function implode;
use function sprintf;
use function str_contains;

/**
 * Class Dialect
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Dialect
 * @since 1.0.0
 */
abstract class Dialect
{

    public string $fieldSeparator = ', ';
    public string $tableSeparator = ', ';
    public array $escapers = ['', ''];
    public string $indentation = '  ';

    /**
     * Escapes the given field.
     *
     * @param string $field
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function escapeField(string $field): string
    {
        if ($field === '*' || str_contains($field, $this->escapers[0])) {
            return $field;
        }

        return $this->escapers[0] . $field . $this->escapers[1];
    }

    /**
     * Escapes the given field with multiple sections.
     *
     * @param string $fields
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function escapeFields(string $fields): string
    {
        if (str_contains($fields, '(') || str_contains($fields, ' ') || str_contains($fields, ':=')) {
            return $fields;
        }

        if (!str_contains($fields, '.')) {
            return $this->escapeField($fields);
        }

        $fields = explode('.', $fields);
        $fields = array_map(fn(string $field): string => $this->escapeField($field), $fields);

        return implode('.', $fields);
    }

    /**
     * Escapes the given table.
     *
     * @param string $table
     *
     * @return string
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    public function escapeTable(string $table): string
    {
        if (!str_contains($table, ' ')) {
            return $this->escapeFields($table);
        }

        $parts = explode(' ', $table, 2);
        $parts[0] = $this->escapeFields($parts[0]);

        return implode(' ', $parts);
    }

    /**
     * Returns a not implemented RuntimeException for the given method.
     *
     * @param string $method
     *
     * @return DatabaseException
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.0
     */
    protected final function notImplemented(string $method): DatabaseException
    {
        return new RuntimeException(sprintf('Method "%s" is not implemented in "%s".', $method, static::class), RuntimeException::ERR_NOT_IMPLEMENTED);
    }

}
