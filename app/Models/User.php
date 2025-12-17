<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'level',
        'avatar_path'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $appends = [
        'avatar_url',
        'is_admin',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar_path) return null;

        // storage/app/public/...
        return Storage::disk('public')->url($this->avatar_path);
    }

    public function getIsAdminAttribute(): bool
    {
        // compatível com diferentes padrões
        return ($this->level ?? null) === 'admin'
            || ($this->role ?? null) === 'admin'
            || (bool)($this->is_admin ?? false);
    }

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }
}
