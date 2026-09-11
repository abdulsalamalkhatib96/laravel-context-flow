<?php

namespace Abdulsalam\LaravelContextFlow\Contracts;

interface IdGenerator
{
    public function generate(): string;
}
