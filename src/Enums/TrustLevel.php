<?php

namespace Abdulsalam\LaravelContextFlow\Enums;

enum TrustLevel: string
{
    case Public = 'public';
    case Partner = 'partner';
    case Internal = 'internal';
    case Local = 'local';
}
