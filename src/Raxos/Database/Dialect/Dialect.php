<?php
declare(strict_types=1);

namespace Raxos\Database\Dialect;

use function array_map;
use function explode;
use function implode;
use function str_contains;

/**
 * Class Dialect
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Dialect
 * @since 1.0.0
 */
abstract readonly class Dialect
{

    /**
     * Dialect constructor.
     *
     * @param array $fieldEscapers
     * @param string $fieldSeparator
     * @param string $tableSeparator
     *
     * @author Bas Milius <bas@mili.us>
     * @since 1.0.17
     */
    public function __construct(
        public array $fieldEscapers = ['', ''],
        public string $fieldSeparator = ', ',
        public string $tableSeparator = ', '
    ) {}

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
        if ($field === '*' || str_contains($field, $this->fieldEscapers[0])) {
            return $field;
        }

        return $this->fieldEscapers[0] . $field . $this->fieldEscapers[1];
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
        if (str_contains($fields, $this->fieldEscapers[0]) || str_contains($fields, '(') || str_contains($fields, ' ') || str_contains($fields, ':=')) {
            return $fields;
        }

        if (!str_contains($fields, '.')) {
            return $this->escapeField($fields);
        }

        $items = explode('.', $fields);
        $items = array_map($this->escapeField(...), $items);

        return implode('.', $items);
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

}
