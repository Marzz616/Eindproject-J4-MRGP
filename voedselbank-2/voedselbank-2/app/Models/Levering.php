<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Levering Model (US-LEV-06, US-WRK-02, US-RAP-02)
 */
class Levering extends Model
{
    protected $table = 'leveringen';

    protected $fillable = [
        'leverancier_id',
        'product_id',
        'aantal',
        'leverdatum',
        'verwerkt',
    ];

    protected $casts = [
        'verwerkt' => 'boolean',
    ];

    public function leverancier()
    {
        return $this->belongsTo(Leverancier::class, 'leverancier_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}