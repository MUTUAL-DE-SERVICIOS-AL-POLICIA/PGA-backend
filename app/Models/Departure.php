<?php

namespace App\Models;

use App\Helpers\Util;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Departure extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [];
    protected $table = "public.departures";

     public function tickets()
    {
        return $this->hasMany(Ticket::class, 'id_permission', 'id');
    }
}
