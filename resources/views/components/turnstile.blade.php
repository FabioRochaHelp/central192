@if (config('services.turnstile.enabled') && filled(config('services.turnstile.site_key')))
    @once
        @push('head')
            <link rel="preconnect" href="https://challenges.cloudflare.com" />
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endpush
    @endonce

    <div class="flex justify-center">
        <div
            class="cf-turnstile"
            data-sitekey="{{ config('services.turnstile.site_key') }}"
            data-theme="light"
        ></div>
    </div>

    @error('cf-turnstile-response')
        <p class="text-center text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
@endif
