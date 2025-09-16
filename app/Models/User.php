<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_picture',
        'default_currency',
        'deleted',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'deleted' => 'boolean',
    ];

    // ✅ tambahkan ini
    protected $appends = ['profile_picture_url'];

    // Relasi
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function investments()
    {
        return $this->hasMany(Investment::class);
    }

    // Accessor untuk URL foto profil
    public function getProfilePictureUrlAttribute()
    {
        if ($this->profile_picture && file_exists(public_path('profile/' . $this->profile_picture))) {
            return url('profile/' . $this->profile_picture);
        }
        return url('profile/default.png'); // fallback kalau tidak ada foto
    }
}
