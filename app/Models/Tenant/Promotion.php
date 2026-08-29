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
        'spot_url',
        'banner_url',
        // Slider administrable (fase 3): texto, versión mobile, orden y vigencia.
        'image_mobile',
        'title',
        'subtitle',
        'button_text',
        'link_type',
        'link_category_id',
        'sort_order',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    protected $appends = ['image_url', 'image_mobile_url'];

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

    /**
     * Imagen vertical para celular. Null cuando el tenant no subió una: el
     * slider cae a la de desktop, que es lo que hacía antes de la fase 3.
     */
    public function getImageMobileUrlAttribute()
    {
        if (!$this->image_mobile || $this->image_mobile === 'imagen-no-disponible.jpg') {
            return null;
        }

        $sub = $this->apply_restaurant ? 'restaurant'.DIRECTORY_SEPARATOR : '';

        return asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'promotions'.DIRECTORY_SEPARATOR.$sub.$this->image_mobile);
    }

    /**
     * Banners vigentes hoy. Sin fechas = siempre visible, que es como se
     * comportaban todos los banners antes de que existiera la programación.
     */
    public function scopeVisibleNow($query)
    {
        $now = now();

        return $query->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * Destino del banner. Centraliza la deducción que estaba repetida en el
     * blade: link_type explícito si existe, si no se infiere de los campos
     * que ya venían poblados.
     */
    public function getLinkHrefAttribute(): ?string
    {
        $type = $this->link_type;

        if (!$type) {
            if (!empty($this->banner_url))   $type = 'url';
            elseif (!empty($this->item_id))  $type = 'product';
            else                             $type = 'none';
        }

        switch ($type) {
            case 'url':
                return $this->banner_url ?: null;
            case 'product':
                return $this->item_id
                    ? url('/ecommerce/item/'.$this->item_id.'/'.$this->id)
                    : null;
            case 'category':
                return $this->link_category_id
                    ? route('tenant.ecommerce.category', ['category' => $this->link_category_id])
                    : null;
        }

        return null;
    }
}