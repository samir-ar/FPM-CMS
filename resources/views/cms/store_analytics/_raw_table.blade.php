{{-- Generic table for Apple's analytics TSV rows. The exact column set
     varies by report and hasn't been observed live yet for this app (first
     instance takes 24-48h to generate), so this renders whatever columns
     Apple actually sends rather than assuming fixed ones. --}}
@if(empty($rows))
    <p class="text-muted">No rows in this report.</p>
@else
    @php $columns = array_keys($rows[0]); @endphp
    <table class="table table-striped table-condensed">
        <thead>
            <tr>
                @foreach($columns as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach(array_slice($rows, 0, 200) as $row)
                <tr>
                    @foreach($columns as $col)
                        <td>{{ $row[$col] ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    @if(count($rows) > 200)
        <p class="text-muted">Showing first 200 of {{ count($rows) }} rows.</p>
    @endif
@endif
