<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'severity',
        'title',
        'message',
        'data',
        'action_url',
        'read_at',
        'deleted',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
        'deleted' => 'boolean',
    ];

    // Relasi
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Accessor sederhana
    protected $appends = ['is_read'];

    public function getIsReadAttribute(): bool
    {
        return !is_null($this->read_at);
    }

    // Scopes berguna
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at')->active();
    }

    public function scopeActive($query)
    {
        return $query->where($query->qualifyColumn('deleted'), false);
    }
}
