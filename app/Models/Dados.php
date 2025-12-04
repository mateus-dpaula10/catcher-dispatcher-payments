<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dados extends Model
{
    protected $table = "dados";

    protected $fillable = [
        'status',
        'amount',
        'amount_cents',
        'first_name',
        'last_name',
        'email',
        'phone',
        'cpf',
        'ip',
        'method',
        'event_time',
        'page_url',
        'client_user_agent',
        'fbp',
        'fbc',
        'fbclid',
        'utm_source',
        'utm_campaign',
        'utm_medium',
        'utm_content',
        'utm_term',
        'pix_key',
        'pix_description',
    ];

    protected $casts = [
        'event_time' => 'integer',
    ];
}
