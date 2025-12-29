<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailMessage extends Model
{
    protected $fillable = [
        'token',
        'external_id',
        'to_email',
        'subject',
        'sent_at',
        'links',
        'open_count',
        'first_opened_at',
        'last_opened_at',
        'click_count',
        'first_clicked_at',
        'last_clicked_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'first_opened_at' => 'datetime',
        'last_opened_at' => 'datetime',
        'first_clicked_at' => 'datetime',
        'last_clicked_at' => 'datetime',
        'links' => 'array',
    ];

    public function events()
    {
        return $this->hasMany(EmailEvent::class);
    }
}
