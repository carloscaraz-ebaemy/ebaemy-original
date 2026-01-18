<?php

namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

class Promotion extends ModelTenant
{
   // protected $table = 'pr';
  
    protected $fillable = [
        'type',
        'description',
        'name',
        'status',
        'image',
        'item_id',
        'apply_restaurant',
        'spot_url'
    ];
    
    protected $appends = ['image_url'];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('active', function (Builder $builder) {
            $builder->where('status', 1);
        });
    }

    public function setDescriptionAttribute($value)
    {
        $this->attributes['description'] = !empty($value) ? $value : 'Banner principal';
    }
    
    public function getImageUrlAttribute()
    {
        if ($this->image && $this->image !== 'imagen-no-disponible.jpg') {
            if ($this->apply_restaurant) {
                return asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'promotions'.DIRECTORY_SEPARATOR.'restaurant'.DIRECTORY_SEPARATOR.$this->image);
            } else {
                return asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'promotions'.DIRECTORY_SEPARATOR.$this->image);
            }
        }
        return asset("/logo/{$this->image}");
    }

   
}