{{-- Tenant detail fields, shared by the tenant form and the lease wizard. Expects $tenant (a Tenant or an array of draft values). --}}
@php
    $value = fn (string $key) => is_array($tenant) ? ($tenant[$key] ?? null) : $tenant->{$key};
@endphp

<div class="grid gap-5 sm:grid-cols-2">
    <x-input name="full_name" :label="__('tenants.fields.full_name')" :value="$value('full_name')" required autocomplete="off" class="sm:col-span-2" />
    <x-input name="phone" type="tel" :label="__('tenants.fields.phone')" :value="$value('phone')" required />
    <x-input name="alt_phone" type="tel" :label="__('tenants.fields.alt_phone')" :value="$value('alt_phone')" optional />
    <x-input name="email" type="email" :label="__('tenants.fields.email')" :value="$value('email')" :hint="__('tenants.fields.email_hint')" optional class="sm:col-span-2" />
    <x-input name="occupation" :label="__('tenants.fields.occupation')" :value="$value('occupation')" optional />
    <x-input name="employer" :label="__('tenants.fields.employer')" :value="$value('employer')" optional />
    <x-input name="id_type" :label="__('tenants.fields.id_type')" :value="$value('id_type')" :hint="__('tenants.fields.id_type_hint')" optional />
    <x-input name="id_number" :label="__('tenants.fields.id_number')" :value="$value('id_number')" optional />
    <x-input name="emergency_contact_name" :label="__('tenants.fields.emergency_contact_name')" :value="$value('emergency_contact_name')" optional />
    <x-input name="emergency_contact_phone" type="tel" :label="__('tenants.fields.emergency_contact_phone')" :value="$value('emergency_contact_phone')" optional />
</div>
