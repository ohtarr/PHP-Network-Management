<?php

namespace App\Models\Credential;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Credential extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'class',
        'username',
        'passkey',
        'description',
        'options',
      ];
}
