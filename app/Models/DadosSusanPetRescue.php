<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DadosSusanPetRescue extends Model
{
    protected $table = "dados_susan_pet_rescues";

    protected $fillable = [
        'external_id',
        'give_payment_id',
        'transaction_id',
        'currency',
        'give_form_id',
        
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
        'popup_5dol',
        'pix_key',
        'pix_description',

        '_country',
        '_region_code',
        '_region',
        '_city'
    ];

    protected $casts = [
        'event_time' => 'integer',
        'popup_5dol' => 'boolean',
    ];
}
