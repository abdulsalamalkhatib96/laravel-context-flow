<?php

namespace Abdulsalam\LaravelContextFlow\Enums;

enum BoundaryType: string
{
    case Http = 'http';
    case Queue = 'queue';
    case Console = 'console';
    case Scheduler = 'scheduler';
    case Process = 'process';
    case Unknown = 'unknown';
}
