<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'code',
        'name_group',
        'state',
        'classifier_id'
    ];


    public function Classifier()
    {
        return $this->belongsTo(Classifier::class);
    }
    public function materials()
    {
        return $this->hasMany(Material::class);
    }
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function Tickets(){
        return $this->hasMany(Ticket::class);
    }
}
