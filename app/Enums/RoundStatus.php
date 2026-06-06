<?php

namespace App\Enums;

enum RoundStatus: string
{
    case Waiting = 'waiting';
    case Playing = 'playing';
    case Revealed = 'revealed';
    case Finished = 'finished';
}
