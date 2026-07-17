@props([
    'sidebar' => false,
])

@if ($sidebar)
    {{-- Só o lockup horizontal; a placa branca e o botão de recolher ficam no cabeçalho do menu. --}}
    <a {{ $attributes->class('flex min-w-0 flex-1 items-center') }}>
        <img
            src="{{ asset('img/logo_cco_horizontal.png') }}"
            alt="{{ config('app.name', 'CCO') }}"
            class="h-auto w-full"
        />
    </a>
@else
    <a {{ $attributes->class('flex items-center') }}>
        <span class="inline-flex items-center rounded-lg bg-white px-3 py-2 shadow-sm ring-1 ring-black/5">
            <img
                src="{{ asset('img/logo_cco_horizontal.png') }}"
                alt="{{ config('app.name', 'CCO') }}"
                class="h-7 w-auto"
            />
        </span>
    </a>
@endif
