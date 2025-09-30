<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCash_Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'petty_cash_products';

    protected $fillable = ['petty_cash_id', 'product_id', 'amount_request', 'number_invoice', 'name_product', 'supplier', 'costDetails', 'costFinal', 'quantity_delivered', 'costDetailsFinal', 'costTotal'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function pettyCashes()
    {
        return $this->belongsTo(PettyCash::class, 'petty_cash_id');
    }
}
