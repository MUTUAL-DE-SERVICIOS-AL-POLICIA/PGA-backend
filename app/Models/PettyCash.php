<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCash extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'petty_cashes';

    protected $guarded = [];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'petty_cash_products', 'petty_cash_id', 'product_id')->withPivot('amount_request', 'number_invoice', 'name_product', 'supplier', 'costDetails', 'costFinal', 'quantity_delivered', 'costDetailsFinal', 'costTotal')->withTimestamps();
    }
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'user_register');
    }
    public function management()
    {
        return $this->belongsTo(Management::class);
    }
    public function fund()
    {
        return $this->belongsTo(Fund::class, 'fund_id');
    }

    public function TypeCashes()
    {
        return $this->belongsTo(TypePetty::class);
    }
}
