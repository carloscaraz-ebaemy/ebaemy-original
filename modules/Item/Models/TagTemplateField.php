<?php

namespace Modules\Item\Models;
use App\Models\Tenant\ModelTenant;

class TagTemplateField extends ModelTenant
{
    public $timestamps = false;

    protected $fillable = [
        'type',
        'column',
        'x',
        'y',
        'width',
        'height',
        'style',
        'barcode',
        'tag_template_id',
        'image',
        'html_id',
        'has_image',
    ];

    protected $casts = [
        'has_image' => 'boolean',
    ];

    public function tagTemplate()
    {
        return $this->belongsTo(TagTemplate::class);
    }

    public function getStyleAttribute($value)
    {
        return json_decode($value, true);
    }

    public function getBarcodeAttribute($value)
    {
        return json_decode($value, true);
    }

}