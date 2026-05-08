<?php

namespace App\Enums;

enum UserType: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Student = 'student';
    case Staff = 'staff';
}
