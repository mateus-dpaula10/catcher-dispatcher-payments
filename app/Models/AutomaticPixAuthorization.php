<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomaticPixAuthorization extends Model
{
    use HasFactory;

    protected $table = "automatic_pix_authorizations";

    protected $fillable = [
        'transfeera_id',
        'amount_cents',
        'cpf',
        'email',
        'cellphone',
        'periodicity',
        'status'
    ];
}
