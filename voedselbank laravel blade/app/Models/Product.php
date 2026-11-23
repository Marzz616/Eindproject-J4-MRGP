<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Product & Voorraad Model (US-VRD-01 t/m US-VRD-12)
 */
class Product extends Model
{
    protected $table = 'producten';

    // US-VRD-01 & US-VRD-12: Naam, categorie, EAN-code (streepjescode), voorraadaantal en allergenen
    protected $fillable = [
        'naam',
        'streepjescode',
        'categorie_id',
        'aantal',
        'allergenen',
    ];

    public function categorie()
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    // US-VRD-04 & US-VRD-05: Relatie met pakketten om te controleren of een product al in een pakket zit
    public function pakketten()
    {
        return $this->belongsToMany(Pakket::class, 'pakket_product', 'product_id', 'pakket_id')
                    ->withPivot('aantal');
    }
}