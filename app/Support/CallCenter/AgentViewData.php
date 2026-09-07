<?php

namespace App\Support\CallCenter;

use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Models\User;
use Illuminate\Support\Str;

/**
 * Maps Call Center Agent users to the approved Blade view shape.
 *
 * Display-only fields such as username / avatar color are derived —
 * they are not persisted database columns.
 */
class AgentViewData
{
    /**
     * Stable palette for initials avatars when no profile photo exists.
     *
     * @var list<string>
     */
    private const AVATAR_COLORS = [
        '#EF4923',
        '#0F766E',
        '#2563EB',
        '#64748B',
        '#7C3AED',
        '#DB2777',
        '#B45309',
        '#047857',
    ];

    /**
     * @return array<string, mixed>
     */
    public function forListItem(User $agent): array
    {
        return $this->baseAgentArray($agent);
    }

    /**
     * @param  array{
     *     catalog: array<string, array{label: string, description: string, permissions: array<string, string>}>,
     *     assigned: list<string>,
     *     states?: array<string, 'granted'|'denied'|'inherited'>
     * }  $permissionState
     * @return array<string, mixed>
     */
    public function forProfile(User $agent, array $permissionState): array
    {
        $base = $this->baseAgentArray($agent);

        return [
            'agent' => array_merge($base, [
                // Order / commission KPIs are unavailable until those domains exist.
                'current_orders' => $this->unavailableCurrentOrders(),
                'overall_performance' => $this->unavailableOverallPerformance(),
                'statistics_available' => false,
            ]),
            'permissionCatalog' => $permissionState['catalog'],
            'assignedPermissions' => $permissionState['assigned'],
            'permissionStates' => $permissionState['states'] ?? [],
            'statisticsAvailable' => false,
        ];
    }

    /**
     * Edit Agent form shape — real agent fields only.
     * Permission checkboxes post to the dedicated permission endpoint.
     *
     * @param  array{
     *     catalog: array<string, array{label: string, description: string, permissions: array<string, string>}>,
     *     assigned: list<string>,
     *     states?: array<string, 'granted'|'denied'|'inherited'>
     * }  $permissionState
     * @return array<string, mixed>
     */
    public function forEdit(User $agent, array $permissionState): array
    {
        return [
            'agent' => $this->baseAgentArray($agent),
            'permissionCatalog' => $permissionState['catalog'],
            'assignedPermissions' => $permissionState['assigned'],
            'permissionStates' => $permissionState['states'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseAgentArray(User $agent): array
    {
        $agent->loadMissing(['profile', 'role']);

        $firstName = trim((string) ($agent->profile?->first_name ?? ''));
        $lastName = trim((string) ($agent->profile?->last_name ?? ''));
        $fullName = trim($firstName.' '.$lastName);
        $commission = $agent->profile?->agent_commission_per_order;
        $commissionAmount = $commission === null || $commission === ''
            ? 0.0
            : (float) $commission;

        $status = $this->uiStatus($agent);
        $profilePhoto = $agent->profile?->profile_photo;

        return [
            'uuid' => $agent->uuid,
            // Route key for show/edit links — users.uuid, not a username column.
            'slug' => $agent->uuid,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName !== '' ? $fullName : 'Call Center Agent',
            'phone' => (string) ($agent->phone ?? ''),
            'email' => (string) ($agent->email ?? ''),
            'nic' => (string) ($agent->profile?->nic ?? ''),
            // Display-only login identifier (O1.1 convention). Not a DB column.
            'username' => $this->displayUsername($firstName, $lastName, $agent->phone),
            'status' => $status,
            'status_label' => $status === 'active' ? 'Active' : 'Inactive',
            'commission' => $commissionAmount,
            'commission_rate' => $commissionAmount,
            'commission_label' => $this->formatCommission($commissionAmount),
            'commission_rate_label' => $this->formatCommission($commissionAmount).' / order',
            'joined_label' => $agent->created_at
                ? $agent->created_at->format('d M Y')
                : '—',
            'initials' => $this->initials($firstName, $lastName),
            'avatar_color' => $this->avatarColor($agent->uuid ?? (string) $agent->id),
            'profile_photo' => $profilePhoto,
            'profile_photo_url' => filled($profilePhoto)
                ? route('files.thumbnail', ['uuid' => $profilePhoto, 'size' => 'md'])
                : null,
            'role_label' => $agent->role?->name ?? 'Call Center Agent',
        ];
    }

    private function uiStatus(User $agent): string
    {
        $status = $agent->status;

        if ($status instanceof UserStatus) {
            return $status === UserStatus::ACTIVE ? 'active' : 'inactive';
        }

        return (string) $status === UserStatus::ACTIVE->value ? 'active' : 'inactive';
    }

    /**
     * Display-only identifier matching the O1.1 preview convention.
     * Not stored on users — agents authenticate with phone + password.
     */
    private function displayUsername(string $firstName, string $lastName, ?string $phone): string
    {
        $first = Str::slug($firstName);
        $last = Str::slug($lastName);

        if ($first !== '' && $last !== '') {
            return 'cca.'.$first.'.'.$last;
        }

        if ($first !== '') {
            return 'cca.'.$first;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone) ?: 'agent';

        return 'cca.'.$digits;
    }

    private function initials(string $firstName, string $lastName): string
    {
        $first = $firstName !== '' ? mb_substr($firstName, 0, 1) : '';
        $last = $lastName !== '' ? mb_substr($lastName, 0, 1) : '';
        $initials = strtoupper($first.$last);

        return $initials !== '' ? $initials : 'CA';
    }

    private function avatarColor(string $seed): string
    {
        $index = abs(crc32($seed)) % count(self::AVATAR_COLORS);

        return self::AVATAR_COLORS[$index];
    }

    public function formatCommission(float $amount): string
    {
        return 'LKR '.number_format($amount, 2, '.', ',');
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableCurrentOrders(): array
    {
        $keys = [
            'lead',
            'first_attempt',
            'second_attempt',
            'third_attempt',
            'hold',
            'confirmed',
            'cancelled',
            'pending',
            'dispatch',
        ];

        $orders = ['unavailable' => true];

        foreach ($keys as $key) {
            $orders[$key] = null;
            $orders[$key.'_label'] = '—';
        }

        return $orders;
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableOverallPerformance(): array
    {
        return [
            'unavailable' => true,
            'total_orders' => null,
            'total_orders_label' => '—',
            'commissions_withdrawn' => null,
            'commissions_withdrawn_label' => '—',
            'pending_commissions' => null,
            'pending_commissions_label' => '—',
            'pending_clearance_orders' => null,
            'pending_clearance_orders_label' => '—',
            'success_rate' => null,
            'success_rate_label' => '—',
        ];
    }
}
