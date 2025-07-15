<?php
declare(strict_types=1);

namespace Raxos\Database\Contract;

use Stringable;

/**
 * Interface QueryLiteralInterface
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Contract
 * @since 1.5.0
 */
interface QueryLiteralInterface extends QueryValueInterface, Stringable {}
