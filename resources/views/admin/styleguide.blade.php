{{-- Developer styleguide (local and testing only). Demo copy here is not translated on purpose. --}}
@php
    $layoutComponent = 'layouts::'.$layout;
@endphp

<x-dynamic-component :component="$layoutComponent" title="Styleguide" heading="Styleguide">
    <div class="space-y-8">
        <x-page-header title="Styleguide" description="Every design-system component, rendered with sample data."
            :breadcrumbs="['Dashboard' => '#', 'Styleguide' => null]">
            <x-slot:actions>
                @foreach ($layouts as $name)
                    <x-button size="sm" :variant="$name === $layout ? 'primary' : 'secondary'" :href="'?layout='.$name">{{ ucfirst($name) }}</x-button>
                @endforeach
            </x-slot:actions>
        </x-page-header>

        <x-card title="Buttons" data-component="button">
            <div class="flex flex-wrap items-center gap-3">
                <x-button type="button">Primary</x-button>
                <x-button type="button" variant="secondary">Secondary</x-button>
                <x-button type="button" variant="ghost">Ghost</x-button>
                <x-button type="button" variant="danger">Danger</x-button>
                <x-button type="button" size="sm" icon="plus">Small with icon</x-button>
                <x-button type="button" :loading="true">Saving</x-button>
                <x-button href="#" variant="secondary">Link button</x-button>
            </div>
        </x-card>

        <x-card title="Form controls" data-component="form">
            <form class="grid gap-5 md:grid-cols-2" onsubmit="event.preventDefault()" data-no-guard>
                <x-input name="full_name" label="Full name" placeholder="Adaeze Okafor" hint="As it appears on their ID." />
                <x-input name="email" type="email" label="Email" optional />
                <x-select name="frequency" label="Rent frequency" placeholder="Choose..." :options="['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual']" value="annual" />
                <x-money-input name="rent_amount" label="Rent amount" :value="125000000" />
                <x-date-input name="start_date" label="Start date" :value="now()" />
                <x-textarea name="notes" label="Notes" rows="3" optional />
                <x-checkbox name="send_invite" label="Send the invite now" hint="The tenant gets an email and SMS." checked />
                <x-toggle name="is_listed" label="Show on the public website" hint="Only vacant or reserved units appear." :checked="true" />
                <x-file-dropzone name="photos[]" label="Photos" multiple :max="5" class="md:col-span-2" />
            </form>
        </x-card>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-component="stat-card">
            <x-stat-card label="Collected this month" value="₦4,250,000.00" delta="+12% on last month" trend="up" icon="banknotes" />
            <x-stat-card label="Total arrears" value="₦1,180,500.00" delta="3 tenants over 90 days" trend="down" icon="exclamation-triangle" />
            <x-stat-card label="Occupancy" value="75%" delta="18 of 24 units" icon="building-office-2" />
            <x-stat-card label="Open repairs" value="7" delta="1 emergency" icon="wrench-screwdriver" href="#" />
        </div>

        <x-card title="Status chips and avatars" data-component="status-chip">
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($roles as $role)
                    <x-status-chip :status="$role" />
                @endforeach
                <x-status-chip label="Paid" color="success" />
                <x-status-chip label="Partial" color="warning" />
                <x-status-chip label="Overdue" color="danger" />
                <x-status-chip label="Scheduled" color="info" />
            </div>
            <div class="mt-4 flex items-center gap-3" data-component="avatar">
                <x-avatar name="Adaeze Okafor" size="sm" />
                <x-avatar name="Musa Bello" />
                <x-avatar name="Tunde" size="lg" />
            </div>
        </x-card>

        <div data-component="table">
            <x-table caption="Sample invoices">
                <x-slot:head>
                    <th>Invoice</th>
                    <th>Tenant</th>
                    <th class="text-right">Amount</th>
                    <th>Status</th>
                </x-slot:head>
                @foreach ([['INV-2026-000041', 'Adaeze Okafor', '₦350,000.00', 'Paid', 'success'], ['INV-2026-000042', 'Musa Bello', '₦1,200,000.00', 'Overdue', 'danger'], ['INV-2026-000043', 'Chika Eze', '₦90,000.00', 'Partial', 'warning']] as [$number, $tenant, $amount, $status, $color])
                    <tr>
                        <td data-label="Invoice" class="font-medium">{{ $number }}</td>
                        <td data-label="Tenant">{{ $tenant }}</td>
                        <td data-label="Amount" class="text-right tabular-nums">{{ $amount }}</td>
                        <td data-label="Status"><x-status-chip :label="$status" :color="$color" /></td>
                    </tr>
                @endforeach
            </x-table>
            <x-pagination :paginator="$paginator" class="mt-4" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-empty-state data-component="empty-state" icon="users" title="No tenants yet"
                text="Add your first tenant to start billing rent." action-label="Add tenant" action-href="#" />

            <x-card title="Timeline" data-component="timeline">
                <x-timeline>
                    <x-timeline.item title="Resolved by vendor" time="2 hours ago" color="success" icon="check">Replaced the kitchen tap.</x-timeline.item>
                    <x-timeline.item title="Scheduled" time="Yesterday" color="info">Visit booked for 12 Nov 2026, 10:00.</x-timeline.item>
                    <x-timeline.item title="Submitted" time="3 days ago">Leaking tap in the kitchen.</x-timeline.item>
                </x-timeline>
            </x-card>
        </div>

        <x-card title="Alerts, stepper, skeleton" data-component="alert">
            <div class="space-y-3">
                <x-alert type="success" title="Payment approved">Receipt RCT-2026-000012 was sent to the tenant.</x-alert>
                <x-alert type="warning">This lease ends in 30 days.</x-alert>
                <x-alert type="danger" title="Could not connect">Check the Paystack secret key.</x-alert>
                <x-alert>Bank transfer proofs are reviewed within one working day.</x-alert>
            </div>
            <x-stepper class="mt-6" data-component="stepper" :steps="['Tenant', 'Unit', 'Lease terms', 'Deposit', 'Review']" :current="3" />
            <div class="mt-6 grid gap-6 md:grid-cols-2" data-component="skeleton">
                <x-skeleton :lines="4" />
                <x-skeleton height="h-32" />
            </div>
        </x-card>

        <x-card title="Tabs" data-component="tabs">
            <x-tabs :tabs="['overview' => 'Overview', 'invoices' => 'Invoices', 'payments' => 'Payments']">
                <x-tabs.panel name="overview">Balance, lease and contacts.</x-tabs.panel>
                <x-tabs.panel name="invoices">Invoices list.</x-tabs.panel>
                <x-tabs.panel name="payments">Payments list.</x-tabs.panel>
            </x-tabs>
        </x-card>

        <x-card title="Overlays" data-component="overlays">
            <div class="flex flex-wrap items-center gap-3">
                <x-button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'demo-modal')">Open modal</x-button>
                <x-button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'demo-slide')">Open slide-over</x-button>
                <x-button type="button" variant="danger" x-data x-on:click="$dispatch('open-modal', 'demo-confirm')">Delete property</x-button>
                <x-button type="button" variant="ghost" x-data x-on:click="window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Saved.', type: 'success' } }))">Show toast</x-button>
                <x-dropdown align="left" data-component="dropdown">
                    <x-slot:trigger class="h-10 border border-border px-4 text-sm font-medium text-ink hover:bg-canvas">
                        Actions <x-heroicon-m-chevron-down class="h-4 w-4" aria-hidden="true" />
                    </x-slot:trigger>
                    <x-dropdown.item href="#" icon="pencil-square">Edit</x-dropdown.item>
                    <x-dropdown.item icon="document-duplicate">Duplicate</x-dropdown.item>
                    <x-dropdown.item icon="trash" danger>Delete</x-dropdown.item>
                </x-dropdown>
            </div>
        </x-card>
    </div>

    <x-modal name="demo-modal" title="Record payment">
        <p>Modal content goes here.</p>
        <x-slot:footer>
            <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'demo-modal')">Cancel</x-button>
            <x-button type="button">Save</x-button>
        </x-slot:footer>
    </x-modal>
    <x-slide-over name="demo-slide" title="Filters">
        <x-select name="property" label="Property" :options="['1' => 'Lekki Gardens', '2' => 'Wuse Court']" placeholder="All properties" />
    </x-slide-over>
    <x-confirm name="demo-confirm" action="#" title="Delete Lekki Gardens?" word="DELETE"
        consequences="The property and its 8 units will be removed from lists. Leases and payment history are kept." confirm-label="Delete property" />
</x-dynamic-component>
