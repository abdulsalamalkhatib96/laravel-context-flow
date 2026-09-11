<?php

namespace Abdulsalam\LaravelContextFlow\Enums;

enum PropagationTarget: string
{
    case Queue = 'queue';
    case InternalHttp = 'http.internal';
    case PartnerHttp = 'http.partner';
    case ExternalHttp = 'http.external';
    case Process = 'process';
    case MessageBus = 'message_bus';
}
