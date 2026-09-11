<?php

namespace Abdulsalam\LaravelContextFlow\Contracts;

interface ContextEnricher
{
    /** @return array<string, mixed> */
    public function enrich(): array;
}
