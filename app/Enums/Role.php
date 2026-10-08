<?php

namespace App\Enums;

enum Role: string
{
    case Murid = 'murid';
    case IbuBapa = 'ibubapa';
    case Guru = 'guru';
    case Admin = 'admin';
}
