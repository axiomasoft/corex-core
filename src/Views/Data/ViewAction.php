<?php

declare(strict_types=1);

namespace CoreX\Views\Data;

enum ViewAction: string
{
    case Read = 'read';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case SetDefault = 'setDefault';
}
