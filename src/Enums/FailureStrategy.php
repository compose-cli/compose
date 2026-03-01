<?php

declare(strict_types=1);

namespace Compose\Enums;

enum FailureStrategy: string
{
    case Abort = 'abort';
    case Continue = 'continue';
    case Retry = 'retry';
    case Rollback = 'rollback';
    case RollbackAll = 'rollback-all';
}
