<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    /**
     * The fillable
     *
     * @var array
     */
    protected $fillable = [
        'image',
        'name',
        'slug'
    ];

    /**
     * products
     *
     * @return void
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * boot
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Generate slug before saving
        static::creating(function ($category) {
            $category->slug = Str::slug($category->name);
        });
    }
}