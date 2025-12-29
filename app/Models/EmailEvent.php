<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailEvent extends Model
{
    protected $fillable = [
        'email_message_id',
        'type',
        'link_key',
        'ip',
        'user_agent'
    ];

    public function message()
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }
}
