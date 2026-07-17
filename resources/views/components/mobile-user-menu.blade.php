<flux:dropdown position="top" align="end">
    <flux:profile
        :initials="auth()->user()->initials()"
        icon-trailing="chevron-down"
    />

    <flux:menu>
        <x-user-profile-menu-items />
    </flux:menu>
</flux:dropdown>
