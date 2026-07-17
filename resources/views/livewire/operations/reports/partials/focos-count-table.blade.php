<table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
        @forelse ($rows as $row)
            <tr><td class="py-2">{{ $row['label'] }}</td><td class="py-2 text-end font-medium tabular-nums">{{ number_format($row['count'], 0, ',', '.') }}</td></tr>
        @empty
            <tr><td class="py-4 text-zinc-500">{{ __('Sem dados.') }}</td></tr>
        @endforelse
    </tbody>
</table>
