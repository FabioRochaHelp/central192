<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
    @foreach (array_chunk($fields, 2) as $row)
        <tr>
            @foreach ($row as $field)
                <td width="50%" class="rpt-field-cell">
                    <div class="rpt-field-box">
                        <span class="rpt-field-label">{{ $field['label'] }}</span>
                        <span class="rpt-field-value">{{ $field['value'] }}</span>
                    </div>
                </td>
            @endforeach
            @if (count($row) === 1)
                <td width="50%" class="rpt-field-cell"></td>
            @endif
        </tr>
    @endforeach
</table>
