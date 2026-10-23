@if(!empty($release))
    <tr>
        <td class="lbl">Datum</td>
        <td class="val">{{ $release['date'] }}</td>
    </tr>
    <tr>
        <td class="lbl">Unterschrift</td>
        <td class="val sig-cell">
            <div style="page-break-inside: avoid; text-align: right;">
                <img src="{{ $release['image'] }}" alt="Standortstempel" style="max-width: 184px; max-height: 80.5px;">
            </div>
        </td>
    </tr>
@endif
