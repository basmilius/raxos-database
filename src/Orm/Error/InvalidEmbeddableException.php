<?php
declare(strict_types=1);

namespace Raxos\Database\Orm\Error;

use Raxos\Contract\Database\Orm\OrmExceptionInterface;
use Raxos\Database\Orm\Attribute\Embeddable;
use Raxos\Error\Exception;
use Throwable;

/**
 * Class InvalidEmbeddableException
 *
 * @author Bas Milius <bas@mili.us>
 * @package Raxos\Database\Orm\Error
 * @since 2.2.0
 */
final class InvalidEmbeddableException extends Exception implements OrmExceptionInterface
{

    /**
     * InvalidEmbeddableException constructor.
     *
     * @param string $modelClass
     * @param string $propertyName
     * @param string $embeddableClass
     * @param Throwable|null $previous
     *
     * @author Bas Milius <bas@mili.us>
     * @since 2.2.0
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string $propertyName,
        public readonly string $embeddableClass,
        ?Throwable $previous = null
    )
    {
        $embeddableAttribute = Embeddable::class;

        parent::__construct(
            'db_orm_invalid_embeddable',
            "Property {$this->modelClass}::\${$this->propertyName} references {$this->embeddableClass} which is not a valid embeddable. Embeddable classes must have the {$embeddableAttribute} attribute and at least one #[Column] property.",
            previous: $previous
        );
    }

}
