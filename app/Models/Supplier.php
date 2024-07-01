<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory;
    use SoftDeletes; //Eliminacion Logica, laravel sabe lo que ya se ha eliminado;

    protected $fillable = [
        'name',
        'nit',
        'cellphone',
        'sales_representative',
        'address',
        'email',
    ];

    protected $dates = ['deleted_at'];


    public function Note_entries(){
        return $this->hasMany(Note_Entrie::class);
    }
}
