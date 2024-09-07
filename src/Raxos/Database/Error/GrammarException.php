<?php
declare(strict_types=1);

namespace Raxos\Database\Error;

use Raxos\Foundation\Error\ExceptionId;

/**
 * Class GrammarException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Error
 * @since 1.1.0
 */
final class GrammarException extends DatabaseException
{

    /**
     * Returns the exception for when a feature is unsupported.
     *
     * @param string $message
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 1.1.0
     */
    public static function unsupported(string $message): self
    {
        return new self(
            ExceptionId::for(__METHOD__),
            'gb_grammar_unsupported',
            $message
        );
    }

}
