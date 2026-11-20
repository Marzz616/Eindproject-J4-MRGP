<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Categorie Model (US-VRD-11 & US-RAP-01)
 */
class Categorie extends Model
{
    protected $table = 'categorieen';

    // US-VRD-11: Categorieën toevoegen en omschrijvingen aanpassen
    protected $fillable = [
        'naam',
        'omschrijving',
    ];

    public function producten()
    {
        return $this->hasMany(Product::class, 'categorie_id');
    }
}