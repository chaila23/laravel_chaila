<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    /**
     * The fillable
     *
     * @var array
     */
    protected $fillable = [
        'customer_id',
        'province_name',
        'city_name',
        'district_name',
        'subdistrict_name',
        'zip_code',
        'full_address',
        'invoice',
        'weight',
        'total',
        'status',
        'snap_token',
    ];

    /**
     * @return void
     */
    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    /**
     * @return void
     */
    public function shipping()
    {
        return $this->hasOne(Shipping::class);
    }

    /**
     * @return void
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}