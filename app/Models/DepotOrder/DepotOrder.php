<?php

namespace App\Models\DepotOrder;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepotOrder extends Model
{
    use HasFactory;

    protected $table = 'depot_orders';

    protected $fillable = [
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
