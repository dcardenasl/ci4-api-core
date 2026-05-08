<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

enum OperationState: string
{
    case SUCCESS  = 'success';
    case ACCEPTED = 'accepted';
    case ERROR    = 'error';
}
