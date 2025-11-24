<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TypeCancellation extends Model
{
    use HasFactory;

    protected $table = 'reason_for_cancellations';
    protected $guarded = [
        
    ];

    public function PettyCashes(){
        return $this->hasMany(PettyCash::class);
    }
}
