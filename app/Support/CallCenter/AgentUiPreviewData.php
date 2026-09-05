<?php

namespace App\Support\CallCenter;

/**
 * Isolated static dataset for O1.1 Call Center Agents UI review.
 *
 * This class must not be used by production business logic, seeders,
 * models, or the permission engine. It will be replaced in O1.2/O1.3.
 */
class AgentUiPreviewData
{
    /**
     * Visual-only permission groups for the Add/Edit/Profile screens.
     * These keys are not real permission slugs and are not persisted.
     *
     * @return array<string, array{label: string, description: string, permissions: array<string, string>}>
     */
    public function permissionCatalog(): array
    {
        return [
            'orders' => [
                'label' => 'Orders',
                'description' => 'Visibility and handling of reseller orders assigned to this agent.',
                'permissions' => [
                    'view_orders' => 'View Orders',
                    'create_orders' => 'Create Orders',
                    'edit_orders' => 'Edit Orders',
                    'assign_orders' => 'Assign Orders',
                ],
            ],
            'call_center' => [
                'label' => 'Call Center',
                'description' => 'Lead follow-up and order confirmation actions during calls.',
                'permissions' => [
                    'view_leads' => 'View Leads',
                    'manage_call_attempts' => 'Manage Call Attempts',
                    'confirm_orders' => 'Confirm Orders',
                    'hold_orders' => 'Hold Orders',
                    'cancel_orders' => 'Cancel Orders',
                ],
            ],
            'customers' => [
                'label' => 'Customers',
                'description' => 'Customer records and previous order history.',
                'permissions' => [
                    'view_customers' => 'View Customers',
                    'view_customer_history' => 'View Customer History',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function agents(): array
    {
        return array_map(fn (array $agent): array => $this->withDisplayFields($agent), [
            [
                'slug' => 'amali-perera',
                'first_name' => 'Amali',
                'last_name' => 'Perera',
                'username' => 'cca.amali.perera',
                'phone' => '077 123 4567',
                'commission_rate' => 100.00,
                'status' => 'active',
                'joined_label' => '12 Mar 2026',
                'avatar_color' => '#EF4923',
                'permissions' => [
                    'view_orders',
                    'create_orders',
                    'edit_orders',
                    'assign_orders',
                    'view_leads',
                    'manage_call_attempts',
                    'confirm_orders',
                    'hold_orders',
                    'cancel_orders',
                    'view_customers',
                    'view_customer_history',
                ],
                'current_orders' => [
                    'lead' => 32,
                    'first_attempt' => 8,
                    'second_attempt' => 5,
                    'third_attempt' => 3,
                    'hold' => 6,
                    'confirmed' => 18,
                    'cancelled' => 4,
                ],
                'overall_performance' => [
                    'total_orders' => 291,
                    'commissions_withdrawn' => 124500.00,
                    'pending_commissions' => 18750.00,
                    'pending_clearance_orders' => 42,
                    'success_rate' => 78.6,
                ],
            ],
            [
                'slug' => 'kasuni-fernando',
                'first_name' => 'Kasuni',
                'last_name' => 'Fernando',
                'username' => 'cca.kasuni.fernando',
                'phone' => '071 234 5678',
                'commission_rate' => 125.00,
                'status' => 'active',
                'joined_label' => '03 Apr 2026',
                'avatar_color' => '#0F766E',
                'permissions' => [
                    'view_orders',
                    'create_orders',
                    'view_leads',
                    'confirm_orders',
                    'view_customers',
                ],
                'current_orders' => [
                    'lead' => 21,
                    'first_attempt' => 6,
                    'second_attempt' => 4,
                    'third_attempt' => 2,
                    'hold' => 3,
                    'confirmed' => 12,
                    'cancelled' => 2,
                ],
                'overall_performance' => [
                    'total_orders' => 198,
                    'commissions_withdrawn' => 98750.00,
                    'pending_commissions' => 11250.00,
                    'pending_clearance_orders' => 28,
                    'success_rate' => 82.1,
                ],
            ],
            [
                'slug' => 'sachini-silva',
                'first_name' => 'Sachini',
                'last_name' => 'Silva',
                'username' => 'cca.sachini.silva',
                'phone' => '076 345 6789',
                'commission_rate' => 80.00,
                'status' => 'active',
                'joined_label' => '18 May 2026',
                'avatar_color' => '#2563EB',
                'permissions' => [
                    'view_orders',
                    'view_leads',
                    'manage_call_attempts',
                    'hold_orders',
                ],
                'current_orders' => [
                    'lead' => 14,
                    'first_attempt' => 5,
                    'second_attempt' => 3,
                    'third_attempt' => 1,
                    'hold' => 4,
                    'confirmed' => 9,
                    'cancelled' => 3,
                ],
                'overall_performance' => [
                    'total_orders' => 126,
                    'commissions_withdrawn' => 45600.00,
                    'pending_commissions' => 6400.00,
                    'pending_clearance_orders' => 19,
                    'success_rate' => 71.5,
                ],
            ],
            [
                'slug' => 'nadeesha-jayawardena',
                'first_name' => 'Nadeesha',
                'last_name' => 'Jayawardena',
                'username' => 'cca.nadeesha.jayawardena',
                'phone' => '075 456 7890',
                'commission_rate' => 100.00,
                'status' => 'inactive',
                'joined_label' => '08 Jan 2026',
                'avatar_color' => '#64748B',
                'permissions' => [
                    'view_orders',
                    'view_leads',
                ],
                'current_orders' => [
                    'lead' => 0,
                    'first_attempt' => 0,
                    'second_attempt' => 0,
                    'third_attempt' => 0,
                    'hold' => 0,
                    'confirmed' => 0,
                    'cancelled' => 0,
                ],
                'overall_performance' => [
                    'total_orders' => 72,
                    'commissions_withdrawn' => 5400.00,
                    'pending_commissions' => 0.00,
                    'pending_clearance_orders' => 0,
                    'success_rate' => 64.2,
                ],
            ],
            [
                'slug' => 'tharushi-wijesinghe',
                'first_name' => 'Tharushi',
                'last_name' => 'Wijesinghe',
                'username' => 'cca.tharushi.wijesinghe',
                'phone' => '070 567 8901',
                'commission_rate' => 150.00,
                'status' => 'active',
                'joined_label' => '22 Jun 2026',
                'avatar_color' => '#7C3AED',
                'permissions' => [
                    'view_orders',
                    'create_orders',
                    'edit_orders',
                    'view_leads',
                    'manage_call_attempts',
                    'confirm_orders',
                    'cancel_orders',
                    'view_customers',
                    'view_customer_history',
                ],
                'current_orders' => [
                    'lead' => 41,
                    'first_attempt' => 11,
                    'second_attempt' => 7,
                    'third_attempt' => 4,
                    'hold' => 8,
                    'confirmed' => 24,
                    'cancelled' => 5,
                ],
                'overall_performance' => [
                    'total_orders' => 368,
                    'commissions_withdrawn' => 186000.00,
                    'pending_commissions' => 28500.00,
                    'pending_clearance_orders' => 55,
                    'success_rate' => 85.6,
                ],
            ],
            [
                'slug' => 'ishara-bandara',
                'first_name' => 'Ishara',
                'last_name' => 'Bandara',
                'username' => 'cca.ishara.bandara',
                'phone' => '072 678 9012',
                'commission_rate' => 90.00,
                'status' => 'inactive',
                'joined_label' => '14 Feb 2026',
                'avatar_color' => '#DB2777',
                'permissions' => [
                    'view_orders',
                    'view_customers',
                    'view_customer_history',
                ],
                'current_orders' => [
                    'lead' => 0,
                    'first_attempt' => 0,
                    'second_attempt' => 0,
                    'third_attempt' => 0,
                    'hold' => 0,
                    'confirmed' => 0,
                    'cancelled' => 0,
                ],
                'overall_performance' => [
                    'total_orders' => 51,
                    'commissions_withdrawn' => 3420.00,
                    'pending_commissions' => 0.00,
                    'pending_clearance_orders' => 0,
                    'success_rate' => 69.8,
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $agent
     * @return array<string, mixed>
     */
    private function withDisplayFields(array $agent): array
    {
        $commissionRate = (float) ($agent['commission_rate'] ?? $agent['commission'] ?? 0);

        $agent['commission_rate'] = $commissionRate;
        // Kept for create/edit form and list compatibility (same mock value).
        $agent['commission'] = $commissionRate;
        $agent['full_name'] = $this->fullName($agent);
        $agent['initials'] = $this->initials($agent);
        $agent['commission_label'] = $this->formatCommission($commissionRate);
        $agent['commission_rate_label'] = $agent['commission_label'].' / order';
        $agent['status_label'] = $agent['status'] === 'active' ? 'Active' : 'Inactive';
        $agent['current_orders'] = $this->withCurrentOrderDisplayFields($agent['current_orders'] ?? []);
        $agent['overall_performance'] = $this->withOverallPerformanceDisplayFields($agent['overall_performance'] ?? []);

        return $agent;
    }

    /**
     * Format static current-order KPI values for Blade display only.
     *
     * @param  array<string, mixed>  $orders
     * @return array<string, mixed>
     */
    private function withCurrentOrderDisplayFields(array $orders): array
    {
        $defaults = [
            'lead' => 0,
            'first_attempt' => 0,
            'second_attempt' => 0,
            'third_attempt' => 0,
            'hold' => 0,
            'confirmed' => 0,
            'cancelled' => 0,
        ];

        $orders = array_merge($defaults, $orders);

        foreach (array_keys($defaults) as $key) {
            $orders[$key.'_label'] = number_format((int) $orders[$key]);
        }

        return $orders;
    }

    /**
     * Format static overall-performance KPI values for Blade display only.
     *
     * @param  array<string, mixed>  $performance
     * @return array<string, mixed>
     */
    private function withOverallPerformanceDisplayFields(array $performance): array
    {
        $defaults = [
            'total_orders' => 0,
            'commissions_withdrawn' => 0.0,
            'pending_commissions' => 0.0,
            'pending_clearance_orders' => 0,
            'success_rate' => 0.0,
        ];

        $performance = array_merge($defaults, $performance);

        $performance['total_orders_label'] = number_format((int) $performance['total_orders']);
        $performance['commissions_withdrawn_label'] = $this->formatCommission((float) $performance['commissions_withdrawn']);
        $performance['pending_commissions_label'] = $this->formatCommission((float) $performance['pending_commissions']);
        $performance['pending_clearance_orders_label'] = number_format((int) $performance['pending_clearance_orders']);
        $performance['success_rate_label'] = number_format((float) $performance['success_rate'], 1).'%';

        return $performance;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        foreach ($this->agents() as $agent) {
            if ($agent['slug'] === $slug) {
                return $agent;
            }
        }

        return null;
    }

    public function formatCommission(float $amount): string
    {
        return 'LKR '.number_format($amount, 2, '.', ',');
    }

    public function fullName(array $agent): string
    {
        return trim($agent['first_name'].' '.$agent['last_name']);
    }

    public function initials(array $agent): string
    {
        return strtoupper(substr($agent['first_name'], 0, 1).substr($agent['last_name'], 0, 1));
    }

    /**
     * @return array<string, mixed>
     */
    public function listViewData(\Illuminate\Http\Request $request): array
    {
        $showEmptyState = $request->boolean('empty');

        return [
            'agents' => $showEmptyState ? [] : $this->agents(),
            'filters' => [
                'search' => (string) $request->query('search', ''),
                'status' => (string) $request->query('status', ''),
            ],
            'showEmptyState' => $showEmptyState,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formViewData(?string $slug = null): array
    {
        $agent = $slug === null ? null : $this->find($slug);

        return [
            'agent' => $agent,
            'permissionCatalog' => $this->permissionCatalog(),
            'assignedPermissions' => $agent['permissions'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profileViewData(string $slug): ?array
    {
        $agent = $this->find($slug);

        if ($agent === null) {
            return null;
        }

        return [
            'agent' => $agent,
            'permissionCatalog' => $this->permissionCatalog(),
            'assignedPermissions' => $agent['permissions'],
        ];
    }
}
