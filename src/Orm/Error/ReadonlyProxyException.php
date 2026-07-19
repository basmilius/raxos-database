<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Error\Exception;

/**
 * Class ReadonlyProxyException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.4.0
 */
final class ReadonlyProxyException extends Exception implements OrmExceptionInterface
{

    /**
     * ReadonlyProxyException constructor.
     *
     * @param string $errorDescription
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    private function __construct(string $errorDescription)
    {
        parent::__construct('db_orm_readonly_proxy', $errorDescription);
    }

    /**
     * Returns an exception for a write attempt through a read-only proxy.
     *
     * @param string $proxyClass
     * @param string $key
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public static function forWrite(string $proxyClass, string $key): self
    {
        return new self("Cannot write to '{$key}' because '{$proxyClass}' is a read-only proxy.");
    }

    /**
     * Returns an exception for a method call through a read-only proxy.
     *
     * @param string $proxyClass
     * @param string $method
     *
     * @return self
     * @author Bas Milius <bas@mili.us>
     * @since 2.4.0
     */
    public static function forCall(string $proxyClass, string $method): self
    {
        return new self("Cannot call '{$method}()' because '{$proxyClass}' is a read-only proxy.");
    }

}
