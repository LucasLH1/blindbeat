<?php

namespace App\Enums;

enum GamePlayerStatus: string
{
    case Active = 'active';
    case Disconnected = 'disconnected';
}
