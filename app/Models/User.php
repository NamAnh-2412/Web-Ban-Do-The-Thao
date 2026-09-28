<?php

namespace App\Models;

use App\Domain\User\Models\User as DomainUser;

/**
 * Alias giữ tương thích Laravel mặc định. Model thật nằm Domain/User.
 */
class User extends DomainUser {}
