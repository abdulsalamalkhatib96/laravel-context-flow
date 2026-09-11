<?php

namespace Abdulsalam\LaravelContextFlow\Contracts;

use Throwable;

interface ExceptionContextReporter
{
    public function report(Throwable $exception): void;
}
