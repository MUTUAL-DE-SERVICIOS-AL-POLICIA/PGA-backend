<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TypePetty extends Model
{
    use HasFactory;

    protected $table = 'type_cashes';
    protected $guarded = [
        
    ];

    public function PettyCashes(){
        return $this->hasMany(PettyCash::class);
    }
}
