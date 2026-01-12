<?php

namespace App\Domain\Permissions\Enums;

enum PermissionScope: string
{
    case Global = 'global';
    case Resource = 'resource';
}