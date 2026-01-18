
<!DOCTYPE html>
<html lang="es">
    <head>
    </head>
    <body style="width: 100%; position:relative">
        @php
            $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
            $length = count($items) * $quantity_per_item;
            $index = 0;
        @endphp
@foreach ($items as $item)
    @for ($i = 0; $i < $quantity_per_item; $i++)
        @foreach ($template->fields as $field)
        
                @php
                    $width = (float)str_replace("px", "",$field->width);
                    $width = $width / 3.7795275591;
                    $height = (float)str_replace("px", "",$field->height);
                    $height = $height  / 3.7795275591;
                    $colour = [0,0,0];
                    $x = (float)str_replace("px", "",$field->x);
                    $y = (float)str_replace("px", "",$field->y);
                    $x = $x / 3.7795275591;
                    $y = $y / 3.7795275591;
                    $style = $field->style;
                @endphp
        
            @if ($field->column === 'barcode' && isset($item->barcode))
            <div style='position: absolute; top: {{ $y }}mm; left: {{ $x }}mm; margin-top:20px;margin-bottom:20px '  >
                @php
                    $height_barcode = (int)($field->barcode['height'] ?? 50);
                    $height_barcode = $height_barcode  / 3.7795275591;
                    $barcode = base64_encode($generator->getBarcode($item->barcode, $generator::TYPE_CODE_128, 1, $height_barcode, $colour));
                    echo '<img width="'.$width .'mm" height="'. $height_barcode. 'mm" style="margin:5px;object-fit:contain" src="data:image/png;base64,' . $barcode .'">';
                @endphp
                @if (filter_var($field->barcode['displayValue'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))
                    @php
                        echo '<div style="font-size: 12px; text-align: center;">' . $item->barcode . '</div>';
                    @endphp
                @endif
            </div>
            @elseif ($field->column === 'image' && isset($field->image))
                @php
                    $url = public_path('storage'.DIRECTORY_SEPARATOR.'tag_images'. DIRECTORY_SEPARATOR . rawurlencode($field->image));
                @endphp
                <div style='display: block ; margin-top:20px;margin-bottom:20px; height:auto; position: absolute; top: {{ $y }}mm; left: {{ $x }}mm;'  >
                    <img src="{{ $url }}" width="{{ $width }}mm" height="{{ $height }}mm"  />
                </div>
            @else

                @php
                $size = isset($style['fontSize']) ? ((float)str_replace("px", "",$style['fontSize']) / 3.7795275591 ): (12/3.7795275591);
                $weight = isset($style['fontWeight']) ? $style['fontWeight'] : 'normal';
                $align = isset($style['textAlign']) ? $style['textAlign'] : 'left';  
                    $color = isset($style['color']) ? $style['color'] : '#000000';

                @endphp
                <div style='display: block; width: {{ $width }}mm  ;height:auto; position: absolute; padding: 10px;top: {{ $y }}mm; left: {{ $x }}mm; font-size: {{ $size }}mm; font-weight: {{ $weight }};text-align: {{ $align }}; text-color:{{ $color }}'  >

                    @if ($field->column === 'unit_type')
                        {{ $item->unit_type->description ?? '' }}
                    
                    @elseif (strpos($field->column, 'attribute') !== false)
                        @php
                            $attribute_number = str_replace('attribute_', '', $field->column);
                            $attribute = collect($item->attributes)->where('attribute_type_id', $attribute_number)->first();
                        @endphp
                        {{ $attribute->value ?? '' }}
                    @else
                        @if ($field->column === 'name')
                            {{ $item->name ??  
                            (
                                $item->description ?? ''
                            ) }}
                        @elseif ($field->column === 'sale_unit_price')
                        <span style="white-space: nowrap;">
                            S/&nbsp;{{ $item->{$field->column} ?? '' }}
                        </span>

                        @else
                            {{ optional($item->{$field->column})->name ?? '' }}
                        @endif
                    @endif

                </div>
            @endif
        @endforeach
        @if ($index + 1 < $length)
            <pagebreak />
        @endif
        @php
            $index++;
        @endphp
    @endfor




@endforeach
</body>
</html>