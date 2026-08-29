{{--
    Tabla de especificaciones técnicas.

    Vivía como componente suelto del theme tecnología, sin nadie que lo
    incluyera y sin fuente para su variable $specs: código muerto. Acá es
    compartido y se alimenta de los atributos que el producto ya tiene
    cargados en el ERP.

    Parámetro: $specs — array asociativo [etiqueta => valor]
--}}
@if(!empty($specs))
<table class="ec-spec-table">
    <tbody>
    @foreach($specs as $label => $value)
        <tr>
            <th scope="row">{{ $label }}</th>
            <td>{{ $value }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

@once
<style>
.ec-spec-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
    border: 1px solid var(--theme-border, #e5e7eb);
    border-radius: 8px;
    overflow: hidden;
}
.ec-spec-table tr:nth-child(even) { background: var(--theme-surface, #f9fafb); }
.ec-spec-table tr + tr { border-top: 1px solid var(--theme-border, #f3f4f6); }
.ec-spec-table th {
    width: 38%;
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
    color: var(--theme-text-primary, #374151);
}
.ec-spec-table td {
    padding: 10px 14px;
    color: var(--theme-text-secondary, #6b7280);
}
@media (max-width: 575px) {
    /* En pantallas angostas la tabla de dos columnas deja el valor en una
       tira ilegible; se apila etiqueta sobre valor. */
    .ec-spec-table th, .ec-spec-table td { display: block; width: auto; }
    .ec-spec-table th { padding-bottom: 2px; }
    .ec-spec-table td { padding-top: 0; }
}
</style>
@endonce
