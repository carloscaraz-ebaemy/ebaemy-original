
<table>
    <thead>
        <tr>
            <td>Sub Diario</td>
            <td>Número de Comprobante</td>
            <td>Fecha de Comprobante</td>
            <td>Código de Moneda</td>
            <td>Glosa Principal</td>
            <td>Tipo de Cambio</td>
            <td>Tipo de Conversión</td>
            <td>Flag de Conversión de Moneda</td>
            <td>Fecha Tipo de Cambio</td>
            <td>Cuenta Contable</td>
            <td>Código de Anexo</td>
            <td>Código de Centro de Costo</td>
            <td>Debe / Haber</td>
            <td>Importe Original</td>
            <td>Importe en Dólares</td>
            <td> Importe en Soles</td>
            <td>Tipo de Documento</td>
            <td>Número de Documento</td>
            <td>Fecha de Documento</td>
            <td>Fecha de Vencimiento</td>
            <td>Código de Area</td>
            <td>Glosa Detalle</td>
            <td>Código de Anexo Auxiliar</td>
            <td>Medio de Pago</td>
            <td>Tipo de Documento de Referencia</td>
            <td>Número de Documento Referencia</td>
            <td>Fecha Documento Referencia</td>
            <td>Nro Máq. Registradora Tipo Doc. Ref.</td>
            <td>Base Imponible Documento Referencia</td>
            <td>IGV Documento Provisión</td>
            <td>Tipo Referencia en estado MQ</td>
            <td>Número Serie Caja Registradora</td>
            <td>Fecha de Operación</td>
            <td>Tipo de Tasa</td>
            <td>Tasa Detracción/Percepción</td>
            <td>Importe Base Detracción/Percepción Dólares</td>
            <td>Importe Base Detracción/Percepción Soles</td>
            <td>Tipo Cambio para 'F'</td>
            <td>Importe de IGV sin derecho crédito fiscal</td>
            <td>Tasa IGV</td> 
            
        </tr>
    </thead>

    <tbody> 
        @foreach($records as $row)
        <tr>
            @foreach ($row as $item)
                <td>{{ $item }}</td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>
