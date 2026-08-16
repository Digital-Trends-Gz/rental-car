<?php

namespace App\Support;

class TenantPermissionCatalog
{
    /**
     * @return array<int, array{name:string,display_name:string,description:string,module:string,action:string,legacy?:string,feature?:string}>
     */
    public static function permissions(): array
    {
        return [
            ...self::legacyPermissions(),
            ...self::granularPermissions(),
        ];
    }

    /**
     * @return array<int, array{name:string,display_name:string,description:string,module:string,action:string,feature?:string}>
     */
    public static function legacyPermissions(): array
    {
        return [
            ['name' => 'tenant-manage-cars', 'display_name' => 'Manage Cars', 'description' => 'Create, edit, and delete cars.', 'module' => 'Fleet Management', 'action' => 'Manage'],
            ['name' => 'tenant-manage-reservations', 'display_name' => 'Manage Reservations', 'description' => 'View and manage all car reservations.', 'module' => 'Bookings & Contracts', 'action' => 'Manage'],
            ['name' => 'tenant-edit-return-reports', 'display_name' => 'Edit Return Reports', 'description' => 'Create and edit return status reports and their extra charges.', 'module' => 'Bookings & Contracts', 'action' => 'Update'],
            ['name' => 'tenant-manage-clients', 'display_name' => 'Manage Clients', 'description' => 'Create and manage client accounts.', 'module' => 'People & Branches', 'action' => 'Manage'],
            ['name' => 'tenant-manage-payments', 'display_name' => 'Manage Payments', 'description' => 'View and handle reservation payments.', 'module' => 'Payments & Offers', 'action' => 'Manage', 'feature' => 'cash_payments'],
            ['name' => 'tenant-view-debtors', 'display_name' => 'View Debtors', 'description' => 'Access debtor lists and unpaid balances.', 'module' => 'Payments & Offers', 'action' => 'View', 'feature' => 'cash_payments'],
            ['name' => 'tenant-collect-debtors', 'display_name' => 'Collect Debtors', 'description' => 'Record collections and follow up debtor payments.', 'module' => 'Payments & Offers', 'action' => 'Collect', 'feature' => 'cash_payments'],
            ['name' => 'tenant-view-financials', 'display_name' => 'View Financials', 'description' => 'View revenue and monetary values across the dashboard and reports.', 'module' => 'Reports & Growth', 'action' => 'View', 'feature' => 'reports_module'],
            ['name' => 'tenant-manage-support', 'display_name' => 'Manage Support', 'description' => 'View and manage support tickets and replies.', 'module' => 'Support & Settings', 'action' => 'Manage'],
            ['name' => 'tenant-manage-employees', 'display_name' => 'Manage Employees', 'description' => 'Manage branch employees and their roles.', 'module' => 'People & Branches', 'action' => 'Manage'],
            ['name' => 'tenant-manage-branches', 'display_name' => 'Manage Branches', 'description' => 'Create and edit company branches.', 'module' => 'People & Branches', 'action' => 'Manage'],
            ['name' => 'tenant-view-reports', 'display_name' => 'View Reports', 'description' => 'Access and export financial and operational reports.', 'module' => 'Reports & Growth', 'action' => 'View', 'feature' => 'reports_module'],
            ['name' => 'tenant-manage-settings', 'display_name' => 'Manage Settings', 'description' => 'Update tenant profile and settings.', 'module' => 'Support & Settings', 'action' => 'Manage'],
        ];
    }

    /**
     * @return array<int, array{name:string,display_name:string,description:string,module:string,action:string,legacy:string,feature?:string}>
     */
    public static function granularPermissions(): array
    {
        return [
            ['name' => 'tenant-dashboard.view', 'display_name' => 'View Dashboard', 'description' => 'Access the tenant dashboard.', 'module' => 'Dashboard', 'action' => 'View', 'legacy' => 'tenant-manage-reservations'],
            ['name' => 'tenant-cars.view', 'display_name' => 'View Cars', 'description' => 'Access the cars list and car details.', 'module' => 'Fleet Management', 'action' => 'View', 'legacy' => 'tenant-manage-cars'],
            ['name' => 'tenant-cars.create', 'display_name' => 'Create Cars', 'description' => 'Register new vehicles.', 'module' => 'Fleet Management', 'action' => 'Create', 'legacy' => 'tenant-manage-cars'],
            ['name' => 'tenant-cars.update', 'display_name' => 'Edit Cars', 'description' => 'Update vehicle data and pricing.', 'module' => 'Fleet Management', 'action' => 'Edit', 'legacy' => 'tenant-manage-cars'],
            ['name' => 'tenant-cars.delete', 'display_name' => 'Delete Cars', 'description' => 'Delete vehicles from the fleet.', 'module' => 'Fleet Management', 'action' => 'Delete', 'legacy' => 'tenant-manage-cars'],
            ['name' => 'tenant-cars.calendar.view', 'display_name' => 'View Car Calendar', 'description' => 'Open car booking calendars.', 'module' => 'Fleet Management', 'action' => 'View Calendar', 'legacy' => 'tenant-manage-cars', 'feature' => 'booking_calendar'],
            ['name' => 'tenant-cars.documents.manage', 'display_name' => 'Manage Car Documents', 'description' => 'Create, update, and delete car documents.', 'module' => 'Fleet Management', 'action' => 'Manage Documents', 'legacy' => 'tenant-manage-cars', 'feature' => 'car_documents'],
            ['name' => 'tenant-maintenance.manage', 'display_name' => 'Manage Maintenance', 'description' => 'Manage maintenance types and records.', 'module' => 'Fleet Management', 'action' => 'Manage Maintenance', 'legacy' => 'tenant-manage-cars', 'feature' => 'maintenance_module'],
            ['name' => 'tenant-violations.manage', 'display_name' => 'Manage Violations', 'description' => 'Manage traffic violations and notices.', 'module' => 'Fleet Management', 'action' => 'Manage Violations', 'legacy' => 'tenant-manage-cars', 'feature' => 'violations_module'],
            ['name' => 'tenant-damages.manage', 'display_name' => 'Manage Damage Reports', 'description' => 'Manage damage reports and repairs.', 'module' => 'Fleet Management', 'action' => 'Manage Damages', 'legacy' => 'tenant-manage-cars', 'feature' => 'damage_reports'],
            ['name' => 'tenant-reservations.view', 'display_name' => 'View Reservations', 'description' => 'Access reservations and booking requests.', 'module' => 'Bookings & Contracts', 'action' => 'View', 'legacy' => 'tenant-manage-reservations'],
            ['name' => 'tenant-reservations.create', 'display_name' => 'Create Reservations', 'description' => 'Create reservations and convert booking requests.', 'module' => 'Bookings & Contracts', 'action' => 'Create', 'legacy' => 'tenant-manage-reservations'],
            ['name' => 'tenant-reservations.update', 'display_name' => 'Edit Reservations', 'description' => 'Update reservation details.', 'module' => 'Bookings & Contracts', 'action' => 'Edit', 'legacy' => 'tenant-manage-reservations'],
            ['name' => 'tenant-reservations.print', 'display_name' => 'Print Reservations', 'description' => 'Export reservation documents.', 'module' => 'Bookings & Contracts', 'action' => 'Print', 'legacy' => 'tenant-manage-reservations', 'feature' => 'pdf_export'],
            ['name' => 'tenant-contracts.view', 'display_name' => 'View Contracts', 'description' => 'Access rental contracts.', 'module' => 'Bookings & Contracts', 'action' => 'View', 'legacy' => 'tenant-manage-reservations'],
            ['name' => 'tenant-contracts.create', 'display_name' => 'Create Contracts', 'description' => 'Create rental contracts.', 'module' => 'Bookings & Contracts', 'action' => 'Create', 'legacy' => 'tenant-manage-reservations'],
            ['name' => 'tenant-contracts.update', 'display_name' => 'Edit Contracts', 'description' => 'Update contract details.', 'module' => 'Bookings & Contracts', 'action' => 'Edit', 'legacy' => 'tenant-manage-reservations'],
            ['name' => 'tenant-contracts.handover', 'display_name' => 'Contract Handover', 'description' => 'Deliver, return, and extend contracts.', 'module' => 'Bookings & Contracts', 'action' => 'Handover', 'legacy' => 'tenant-manage-reservations'],
            ['name' => 'tenant-contracts.return-reports.view', 'display_name' => 'View Return Reports', 'description' => 'Open contract return status reports.', 'module' => 'Bookings & Contracts', 'action' => 'View Return Reports', 'legacy' => 'tenant-manage-reservations'],
            ['name' => 'tenant-contracts.return-reports.update', 'display_name' => 'Edit Return Reports', 'description' => 'Create and edit return reports and extra charges.', 'module' => 'Bookings & Contracts', 'action' => 'Edit Return Reports', 'legacy' => 'tenant-edit-return-reports'],
            ['name' => 'tenant-contracts.pdf', 'display_name' => 'Export Contract PDF', 'description' => 'Export contracts and return reports to PDF.', 'module' => 'Bookings & Contracts', 'action' => 'Export PDF', 'legacy' => 'tenant-manage-reservations', 'feature' => 'pdf_export'],
            ['name' => 'tenant-clients.view', 'display_name' => 'View Clients', 'description' => 'Access client profiles.', 'module' => 'People & Branches', 'action' => 'View', 'legacy' => 'tenant-manage-clients'],
            ['name' => 'tenant-clients.create', 'display_name' => 'Create Clients', 'description' => 'Create client accounts.', 'module' => 'People & Branches', 'action' => 'Create', 'legacy' => 'tenant-manage-clients'],
            ['name' => 'tenant-clients.update', 'display_name' => 'Manage Clients', 'description' => 'Manage client documents, notes, and status.', 'module' => 'People & Branches', 'action' => 'Manage', 'legacy' => 'tenant-manage-clients'],
            ['name' => 'tenant-branches.view', 'display_name' => 'View Branches', 'description' => 'Access branch records.', 'module' => 'People & Branches', 'action' => 'View', 'legacy' => 'tenant-manage-branches'],
            ['name' => 'tenant-branches.create', 'display_name' => 'Create Branches', 'description' => 'Create company branches.', 'module' => 'People & Branches', 'action' => 'Create', 'legacy' => 'tenant-manage-branches'],
            ['name' => 'tenant-branches.update', 'display_name' => 'Edit Branches', 'description' => 'Update company branches.', 'module' => 'People & Branches', 'action' => 'Edit', 'legacy' => 'tenant-manage-branches'],
            ['name' => 'tenant-branches.delete', 'display_name' => 'Delete Branches', 'description' => 'Delete company branches.', 'module' => 'People & Branches', 'action' => 'Delete', 'legacy' => 'tenant-manage-branches'],
            ['name' => 'tenant-employees.view', 'display_name' => 'View Employees', 'description' => 'Access employee records.', 'module' => 'People & Branches', 'action' => 'View', 'legacy' => 'tenant-manage-employees'],
            ['name' => 'tenant-employees.create', 'display_name' => 'Create Employees', 'description' => 'Create employee accounts.', 'module' => 'People & Branches', 'action' => 'Create', 'legacy' => 'tenant-manage-employees'],
            ['name' => 'tenant-employees.update', 'display_name' => 'Edit Employees', 'description' => 'Update employee details and branch access.', 'module' => 'People & Branches', 'action' => 'Edit', 'legacy' => 'tenant-manage-employees'],
            ['name' => 'tenant-employees.delete', 'display_name' => 'Delete Employees', 'description' => 'Delete employee accounts.', 'module' => 'People & Branches', 'action' => 'Delete', 'legacy' => 'tenant-manage-employees'],
            ['name' => 'tenant-roles.manage', 'display_name' => 'Manage Roles & Permissions', 'description' => 'Create roles and assign permissions.', 'module' => 'People & Branches', 'action' => 'Manage Roles', 'legacy' => 'tenant-manage-employees', 'feature' => 'roles_and_permissions'],
            ['name' => 'tenant-payments.view', 'display_name' => 'View Payments', 'description' => 'Access payment records.', 'module' => 'Payments & Offers', 'action' => 'View', 'legacy' => 'tenant-manage-payments', 'feature' => 'cash_payments'],
            ['name' => 'tenant-payments.collect', 'display_name' => 'Collect Payments', 'description' => 'Record cash payments and debtor collections.', 'module' => 'Payments & Offers', 'action' => 'Collect', 'legacy' => 'tenant-collect-debtors', 'feature' => 'cash_payments'],
            ['name' => 'tenant-debtors.view', 'display_name' => 'View Debtors', 'description' => 'Access debtor lists and unpaid balances.', 'module' => 'Payments & Offers', 'action' => 'View Debtors', 'legacy' => 'tenant-view-debtors', 'feature' => 'cash_payments'],
            ['name' => 'tenant-discounts.manage', 'display_name' => 'Manage Discounts', 'description' => 'Approve discounts, coupons, and automatic discount rules.', 'module' => 'Payments & Offers', 'action' => 'Manage Discounts', 'legacy' => 'tenant-manage-payments', 'feature' => 'cash_payments'],
            ['name' => 'tenant-financials.view', 'display_name' => 'View Financial Amounts', 'description' => 'See revenue and monetary values.', 'module' => 'Reports & Growth', 'action' => 'View Financials', 'legacy' => 'tenant-view-financials', 'feature' => 'reports_module'],
            ['name' => 'tenant-reports.view', 'display_name' => 'View Reports', 'description' => 'Access operational and financial reports.', 'module' => 'Reports & Growth', 'action' => 'View', 'legacy' => 'tenant-view-reports', 'feature' => 'reports_module'],
            ['name' => 'tenant-reports.export', 'display_name' => 'Export Reports', 'description' => 'Export reports to PDF and Excel.', 'module' => 'Reports & Growth', 'action' => 'Export', 'legacy' => 'tenant-view-reports', 'feature' => 'reports_module'],
            ['name' => 'tenant-ai-insights.manage', 'display_name' => 'Manage AI Insights', 'description' => 'Generate and analyze AI insight reports.', 'module' => 'Reports & Growth', 'action' => 'AI Insights', 'legacy' => 'tenant-view-reports', 'feature' => 'reports_module'],
            ['name' => 'tenant-support.view', 'display_name' => 'View Support', 'description' => 'Access support tickets.', 'module' => 'Support & Settings', 'action' => 'View', 'legacy' => 'tenant-manage-support'],
            ['name' => 'tenant-support.reply', 'display_name' => 'Reply to Support', 'description' => 'Reply to and close support tickets.', 'module' => 'Support & Settings', 'action' => 'Reply', 'legacy' => 'tenant-manage-support'],
            ['name' => 'tenant-settings.view', 'display_name' => 'View Settings', 'description' => 'Access tenant settings pages.', 'module' => 'Support & Settings', 'action' => 'View', 'legacy' => 'tenant-manage-settings'],
            ['name' => 'tenant-settings.update', 'display_name' => 'Update Settings', 'description' => 'Update tenant settings and website content.', 'module' => 'Support & Settings', 'action' => 'Update', 'legacy' => 'tenant-manage-settings'],
            ['name' => 'tenant-translations.manage', 'display_name' => 'Manage Translations', 'description' => 'Edit tenant translation overrides.', 'module' => 'Support & Settings', 'action' => 'Translations', 'legacy' => 'tenant-manage-settings'],
            ['name' => 'tenant-seo.manage', 'display_name' => 'Manage SEO', 'description' => 'Edit SEO settings and audit pages.', 'module' => 'Support & Settings', 'action' => 'SEO', 'legacy' => 'tenant-manage-settings'],
            ['name' => 'tenant-billing.manage', 'display_name' => 'Manage Payment Gateway', 'description' => 'Manage payment provider and Stripe Connect settings.', 'module' => 'Support & Settings', 'action' => 'Payment Gateway', 'legacy' => 'tenant-manage-settings', 'feature' => 'stripe_connect'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function legacyExpansionMap(): array
    {
        $map = [];

        foreach (self::granularPermissions() as $permission) {
            $legacy = $permission['legacy'] ?? null;
            if (!$legacy) {
                continue;
            }

            $map[$legacy] ??= [];
            $map[$legacy][] = $permission['name'];
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    public static function legacyPermissionNames(): array
    {
        return collect(self::legacyPermissions())
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @param  iterable<string>  $permissionNames
     * @return list<string>
     */
    public static function expandNames(iterable $permissionNames): array
    {
        $names = collect($permissionNames)
            ->map(fn ($name) => (string) $name)
            ->filter()
            ->values();
        $map = self::legacyExpansionMap();

        return $names
            ->flatMap(fn (string $name) => [$name, ...($map[$name] ?? [])])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function legacyNamesFor(string $permissionName): array
    {
        return collect(self::granularPermissions())
            ->filter(fn (array $permission): bool => $permission['name'] === $permissionName && !empty($permission['legacy']))
            ->pluck('legacy')
            ->unique()
            ->values()
            ->all();
    }

    public static function metadataFor(string $permissionName): array
    {
        return collect(self::permissions())->firstWhere('name', $permissionName) ?? [];
    }
}
