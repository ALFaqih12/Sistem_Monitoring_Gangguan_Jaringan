<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Model Admin untuk single user authentication.
 * Extends Authenticatable untuk support Auth facade.
 */
class Admin extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Get the password for the user.
     * Required untuk Auth::attempt()
     */
    public function getAuthPassword()
    {
        return $this->password;
    }
}
