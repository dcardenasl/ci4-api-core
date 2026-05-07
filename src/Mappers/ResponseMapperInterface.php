<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Mappers;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

interface ResponseMapperInterface
{
    public function map(object $entity): DataTransferObjectInterface;
}
