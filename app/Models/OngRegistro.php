<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OngRegistro extends Model
{
    protected $table = 'ong_registros';

    protected $fillable = [
        'name','email','cnpj','cnpj_not_available','phone','foundation_date','animal_count','caregiver_count',
        'description',
        'street','number','complement','district','city','state','zip',
        'facebook','facebook_not_available','instagram','instagram_not_available','website','website_not_available',
        'portion_value','medicines_value','veterinarian_value','collaborators_value',
        'other_costs_value','other_costs_description',
        'photo_urls',
        'monthly_costs',
        'ip','user_agent','source_tag','source_url'
    ];

    protected $casts = [
        'cnpj_not_available' => 'boolean',
        'facebook_not_available' => 'boolean',
        'instagram_not_available' => 'boolean',
        'website_not_available' => 'boolean',
        'foundation_date' => 'date',
        'photo_urls' => 'array',
        'monthly_costs' => 'array',
        'portion_value' => 'float',
        'medicines_value' => 'float',
        'veterinarian_value' => 'float',
        'collaborators_value' => 'float',
        'other_costs_value' => 'float',
    ];
}
