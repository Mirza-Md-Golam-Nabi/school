<?php

namespace App\Traits;

use App\Enums\UserType;

trait HasUserType
{
    public function isSuperAdmin(): bool
    {
        return $this->user_type === UserType::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->user_type === UserType::Admin;
    }

    public function isTeacher(): bool
    {
        return $this->user_type === UserType::Teacher;
    }

    public function isStudent(): bool
    {
        return $this->user_type === UserType::Student;
    }

    public function isStaff(): bool
    {
        return $this->user_type === UserType::Staff;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}
