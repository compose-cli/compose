<?php

namespace Compose\Enums;

enum FailureStrategy: string
{
    case Abort = 'abort';
    case Continue = 'continue';
}
