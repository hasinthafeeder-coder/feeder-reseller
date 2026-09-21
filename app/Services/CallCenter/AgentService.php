<?php

namespace App\Services\CallCenter;

use Feeder\Core\Authorization\Services\PermissionService;
use Feeder\Core\Authorization\Services\UserPermissionService;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\User;
use Feeder\Core\Models\UserProfile;
use Feeder\Core\Services\UuidService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Reseller Call Center Agent domain service.
 *
 * Agents are employee users with the RESELLER call-center-agent role.
 * There is no separate call_center_agents table.
 *
 * Controllers must pass the authenticated actor; company scope is always
 * derived from actor.company_id and must never be client-supplied.
 */
class AgentService
{
    public const PERMISSION_VIEW = 'call_center.agents.view';

    public const PERMISSION_CREATE = 'call_center.agents.create';

    public const PERMISSION_UPDATE = 'call_center.agents.update';

    public const PERMISSION_ACTIVATE = 'call_center.agents.activate';

    public const PERMISSION_DEACTIVATE = 'call_center.agents.deactivate';

    public const PERMISSION_COMMISSION_UPDATE = 'call_center.agents.commission.update';

    public const PERMISSION_PERMISSIONS_UPDATE = 'call_center.agents.permissions.update';

    public const ROLE_SLUG = 'call-center-agent';

    /**
     * Management permissions that must never be assigned to Agents
     * through the operational override mechanism.
     *
     * @var list<string>
     */
    public const MANAGEMENT_PERMISSION_SLUGS = [
        self::PERMISSION_VIEW,
        self::PERMISSION_CREATE,
        self::PERMISSION_UPDATE,
        self::PERMISSION_ACTIVATE,
        self::PERMISSION_DEACTIVATE,
        self::PERMISSION_COMMISSION_UPDATE,
        self::PERMISSION_PERMISSIONS_UPDATE,
    ];

    /**
     * Explicit operational allowlist for Agent permission overrides.
     *
     * Owners may grant / revoke these on individual Agents. Management
     * permissions (call_center.agents.*) are never included.
     *
     * @var list<string>
     */
    private const OPERATIONAL_PERMISSION_ALLOWLIST = [
        'orders.view',
        'orders.create',
        'orders.update',
        'orders.status.update',
        'orders.comments.create',
        'dashboard.view',
        'products.view',
        'team.structure.view',
        'customers.view',
    ];

    /**
     * @var Collection<int, Permission>|null
     */
    private ?Collection $assignablePermissionsMemo = null;

    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly UserPermissionService $userPermissionService,
    ) {}

    private const LIST_PER_PAGE = 15;

    /**
     * Company-scoped Call Center Agent query for the actor's reseller company.
     *
     * Suitable for pagination / filtering by callers.
     */
    public function listForCompany(
        User $actor,
        ?string $search = null,
        ?string $statusFilter = null,
    ): Builder {
        $this->assertActorCan($actor, self::PERMISSION_VIEW);

        return $this->applyListFilters(
            $this->agentQueryForCompany((int) $actor->company_id)
                ->with(['profile', 'role'])
                ->orderBy('users.id'),
            $search,
            $statusFilter,
        );
    }

    /**
     * Paginated company-scoped Agent list with search / status filters.
     */
    public function paginateForCompany(
        User $actor,
        ?string $search = null,
        ?string $statusFilter = null,
        int $perPage = self::LIST_PER_PAGE,
    ): LengthAwarePaginator {
        return $this->listForCompany($actor, $search, $statusFilter)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Resolve one Agent inside the actor's company boundary.
     *
     * Cross-company UUIDs are treated as not found (no existence leak).
     */
    public function findForManagement(User $actor, string $uuid): User
    {
        $this->assertActorCan($actor, self::PERMISSION_VIEW);

        $agent = $this->agentQueryForCompany((int) $actor->company_id)
            ->where('users.uuid', $uuid)
            ->with(['profile', 'role', 'directPermissions'])
            ->first();

        if ($agent === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$uuid]);
        }

        return $agent;
    }

    /**
     * Read-only permission catalog + assigned operational permission slugs
     * for Agent profile display. Management permissions are excluded.
     *
     * @return array{
     *     catalog: array<string, array{label: string, description: string, permissions: array<string, string>}>,
     *     assigned: list<string>,
     *     states: array<string, 'granted'|'denied'|'inherited'>
     * }
     */
    public function profilePermissionDisplay(User $actor, User $agent): array
    {
        $this->assertActorCan($actor, self::PERMISSION_VIEW);

        $managed = $this->resolveManagedAgent($actor, $agent);
        $assignable = $this->assignablePermissions();
        $overrides = $managed->directPermissions()
            ->get()
            ->keyBy('slug');

        $assigned = [];
        $states = [];

        foreach ($assignable as $permission) {
            $override = $overrides->get($permission->slug);

            if ($override !== null) {
                $allowed = (bool) $override->pivot->allowed;
                $states[$permission->slug] = $allowed ? 'granted' : 'denied';

                if ($allowed) {
                    $assigned[] = $permission->slug;
                }

                continue;
            }

            $states[$permission->slug] = 'inherited';

            if ($this->permissionService->hasPermission($managed, $permission->slug)) {
                $assigned[] = $permission->slug;
            }
        }

        return [
            'catalog' => $this->buildOperationalCatalog($assignable),
            'assigned' => $assigned,
            'states' => $states,
        ];
    }

    /**
     * Operational RESELLER permissions that may be shown as Agent checkboxes.
     *
     * @return array<string, array{label: string, description: string, permissions: array<string, string>}>
     */
    public function operationalPermissionCatalog(): array
    {
        return $this->buildOperationalCatalog($this->assignablePermissions());
    }

    /**
     * Apply list search / status filters while remaining company-scoped.
     *
     * UI status values: active → ACTIVE, inactive → SUSPENDED.
     */
    protected function applyListFilters(
        Builder $query,
        ?string $search,
        ?string $statusFilter,
    ): Builder {
        $search = trim((string) $search);

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('users.phone', 'like', $like)
                    ->orWhere('users.email', 'like', $like)
                    ->orWhereHas('profile', function (Builder $profileQuery) use ($like): void {
                        $profileQuery->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhereRaw(
                                "CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?",
                                [$like]
                            );
                    });
            });
        }

        $statusFilter = strtolower(trim((string) $statusFilter));

        if ($statusFilter === 'active') {
            $query->where('users.status', UserStatus::ACTIVE->value);
        } elseif ($statusFilter === 'inactive') {
            $query->where('users.status', UserStatus::SUSPENDED->value);
        }

        return $query;
    }

    /**
     * Create a Call Center Agent for the actor's reseller company.
     *
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     phone: string,
     *     password: string,
     *     nic?: string|null,
     *     agent_commission_per_order: mixed,
     *     permissions?: list<int|string>
     * }  $data
     */
    public function create(User $actor, array $data): User
    {
        $this->assertActorCan($actor, self::PERMISSION_CREATE);

        $companyId = (int) $actor->company_id;
        $phone = $this->normalizeRequiredString($data['phone'] ?? null, 'phone');
        $firstName = $this->normalizeRequiredString($data['first_name'] ?? null, 'first_name');
        $lastName = $this->normalizeRequiredString($data['last_name'] ?? null, 'last_name');
        $password = $this->normalizeRequiredString($data['password'] ?? null, 'password');
        $nic = $this->normalizeOptionalNic($data['nic'] ?? null);
        $commission = $this->normalizeCommission($data['agent_commission_per_order'] ?? null);
        $createPermissionGrants = $this->filterCreatePermissionGrants($data['permissions'] ?? []);

        $this->assertPhoneAvailable($phone);
        $this->assertNicAvailable($nic);

        $role = $this->resolveCallCenterAgentRole();
        $email = $this->generatedResellerEmail($phone);
        $canAssignPermissions = $this->permissionService->hasPermission(
            $actor,
            self::PERMISSION_PERMISSIONS_UPDATE
        );

        return DB::transaction(function () use (
            $companyId,
            $phone,
            $email,
            $password,
            $role,
            $firstName,
            $lastName,
            $nic,
            $commission,
            $createPermissionGrants,
            $canAssignPermissions,
        ): User {
            /** @var User $user */
            $user = User::query()->create([
                'uuid' => UuidService::generate(),
                'company_id' => $companyId,
                'role_id' => $role->id,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($password),
                'user_type' => UserType::EMPLOYEE->value,
                'status' => UserStatus::ACTIVE->value,
                'phone_verified_at' => now(),
                'is_master_reseller' => false,
            ]);

            $profile = $user->profile;

            if ($profile === null) {
                $profile = new UserProfile;
                $profile->uuid = UuidService::generate();
                $profile->user_id = $user->id;
            }

            $profile->first_name = $firstName;
            $profile->last_name = $lastName;
            $profile->nic = $nic;
            $profile->agent_commission_per_order = $commission;
            $profile->save();

            if ($createPermissionGrants !== [] && $canAssignPermissions) {
                $this->userPermissionService->syncOverrides($user, $createPermissionGrants);
            }

            return $user->fresh(['profile', 'role', 'directPermissions']);
        });
    }

    /**
     * Update editable Agent account / profile fields.
     *
     * Does not mutate company_id, user_type, role_id, status, or commission.
     *
     * @param  array{
     *     first_name?: string,
     *     last_name?: string,
     *     phone?: string,
     *     nic?: string|null,
     *     password?: string|null
     * }  $data
     */
    public function update(User $actor, User $agent, array $data): User
    {
        $this->assertActorCan($actor, self::PERMISSION_UPDATE);

        $managed = $this->resolveManagedAgent($actor, $agent);
        $this->assertNotSelf($actor, $managed);

        $firstName = array_key_exists('first_name', $data)
            ? $this->normalizeRequiredString($data['first_name'], 'first_name')
            : null;
        $lastName = array_key_exists('last_name', $data)
            ? $this->normalizeRequiredString($data['last_name'], 'last_name')
            : null;
        $phone = array_key_exists('phone', $data)
            ? $this->normalizeRequiredString($data['phone'], 'phone')
            : null;
        $nicProvided = array_key_exists('nic', $data);
        $nic = $nicProvided ? $this->normalizeOptionalNic($data['nic']) : null;
        $password = array_key_exists('password', $data) && $data['password'] !== null && $data['password'] !== ''
            ? $this->normalizeRequiredString($data['password'], 'password')
            : null;

        if ($phone !== null) {
            $this->assertPhoneAvailable($phone, $managed->id);
        }

        if ($nicProvided) {
            $this->assertNicAvailable($nic, $managed->profile?->id);
        }

        return DB::transaction(function () use (
            $managed,
            $firstName,
            $lastName,
            $phone,
            $nicProvided,
            $nic,
            $password,
        ): User {
            if ($phone !== null) {
                $managed->phone = $phone;
                // Keep system email aligned with the phone-login convention.
                $managed->email = $this->generatedResellerEmail($phone);
            }

            if ($password !== null) {
                $managed->password = Hash::make($password);
            }

            $managed->save();

            $profile = $managed->profile;

            if ($profile === null) {
                $profile = new UserProfile;
                $profile->uuid = UuidService::generate();
                $profile->user_id = $managed->id;
            }

            if ($firstName !== null) {
                $profile->first_name = $firstName;
            }

            if ($lastName !== null) {
                $profile->last_name = $lastName;
            }

            if ($nicProvided) {
                $profile->nic = $nic;
            }

            $profile->save();

            return $managed->fresh(['profile', 'role']);
        });
    }

    public function activate(User $actor, User $agent): User
    {
        $this->assertActorCan($actor, self::PERMISSION_ACTIVATE);

        $managed = $this->resolveManagedAgent($actor, $agent);
        $this->assertNotSelf($actor, $managed);

        $managed->status = UserStatus::ACTIVE;
        $managed->save();

        return $managed->fresh(['profile', 'role']);
    }

    public function deactivate(User $actor, User $agent): User
    {
        $this->assertActorCan($actor, self::PERMISSION_DEACTIVATE);

        $managed = $this->resolveManagedAgent($actor, $agent);
        $this->assertNotSelf($actor, $managed);

        $managed->status = UserStatus::SUSPENDED;
        $managed->save();

        return $managed->fresh(['profile', 'role']);
    }

    /**
     * Update the Agent's current commission rate only.
     * Does not write earnings / ledger / payout history.
     */
    public function updateCommission(User $actor, User $agent, mixed $amount): User
    {
        $this->assertActorCan($actor, self::PERMISSION_COMMISSION_UPDATE);

        $managed = $this->resolveManagedAgent($actor, $agent);
        $this->assertNotSelf($actor, $managed);

        $commission = $this->normalizeCommission($amount);

        return DB::transaction(function () use ($managed, $commission): User {
            $profile = $managed->profile;

            if ($profile === null) {
                $profile = new UserProfile;
                $profile->uuid = UuidService::generate();
                $profile->user_id = $managed->id;
                $profile->first_name = '';
                $profile->last_name = '';
            }

            $profile->agent_commission_per_order = $commission;
            $profile->save();

            return $managed->fresh(['profile', 'role']);
        });
    }

    /**
     * Replace Agent user-level permission overrides.
     *
     * Map format: [permissionSlug|permissionId => allowed(bool)]
     * Management permissions (call_center.agents.*) are rejected.
     * Permissions that are not RESELLER operational permissions are rejected.
     *
     * @param  array<int|string, bool>  $permissionOverrides
     */
    public function syncPermissions(User $actor, User $agent, array $permissionOverrides): User
    {
        $this->assertActorCan($actor, self::PERMISSION_PERMISSIONS_UPDATE);

        $managed = $this->resolveManagedAgent($actor, $agent);
        $this->assertNotSelf($actor, $managed);

        $payload = $this->resolveAssignableOverridePayload($permissionOverrides);

        DB::transaction(function () use ($managed, $payload): void {
            $this->userPermissionService->syncOverrides($managed, $payload);
        });

        return $managed->fresh(['profile', 'role', 'directPermissions']);
    }

    /**
     * Checkbox-oriented replacement of Agent overrides.
     *
     * Selected identifiers become allowed=true grants.
     * Denied identifiers become allowed=false overrides.
     * Any previous override not listed is revoked (role inheritance resumes).
     *
     * @param  list<int|string>  $grantedIdentifiers
     * @param  list<int|string>  $deniedIdentifiers
     */
    public function syncPermissionSelections(
        User $actor,
        User $agent,
        array $grantedIdentifiers,
        array $deniedIdentifiers = [],
    ): User {
        $grantedPayload = $grantedIdentifiers === []
            ? []
            : $this->resolveAssignableOverridePayload(
                array_fill_keys(array_values($grantedIdentifiers), true)
            );

        $deniedPayload = $deniedIdentifiers === []
            ? []
            : $this->resolveAssignableOverridePayload(
                array_fill_keys(array_values($deniedIdentifiers), false)
            );

        if (array_intersect_key($grantedPayload, $deniedPayload) !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'A permission cannot be both granted and denied in the same request.',
            ]);
        }

        return $this->syncPermissions($actor, $agent, $grantedPayload + $deniedPayload);
    }

    /**
     * Permissions that may be assigned to Agents as user-level overrides.
     *
     * @return Collection<int, Permission>
     */
    public function assignablePermissions(): Collection
    {
        if ($this->assignablePermissionsMemo !== null) {
            return $this->assignablePermissionsMemo;
        }

        $query = Permission::query()
            ->with('portal')
            ->whereNull('deleted_at')
            ->whereHas('portal', fn (Builder $portalQuery) => $portalQuery->where('code', PortalCode::RESELLER->value))
            ->where(function (Builder $query): void {
                $query->where('slug', 'not like', 'call_center.agents.%')
                    ->whereNotIn('slug', self::MANAGEMENT_PERMISSION_SLUGS);
            })
            ->orderBy('module')
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('id');

        if (self::OPERATIONAL_PERMISSION_ALLOWLIST !== []) {
            $query->whereIn('slug', self::OPERATIONAL_PERMISSION_ALLOWLIST);
        }

        return $this->assignablePermissionsMemo = $query->get();
    }

    /**
     * Whether the user satisfies the Call Center Agent identity invariant
     * for the given company (when provided).
     */
    public function isCallCenterAgent(User $user, ?int $companyId = null): bool
    {
        if ($user->trashed()) {
            return false;
        }

        $userType = $user->user_type instanceof UserType
            ? $user->user_type->value
            : (string) $user->user_type;

        if ($userType !== UserType::EMPLOYEE->value) {
            return false;
        }

        if ($companyId !== null && (int) $user->company_id !== $companyId) {
            return false;
        }

        $user->loadMissing('role.portal');

        $role = $user->role;

        if ($role === null || $role->trashed()) {
            return false;
        }

        if ($role->slug !== self::ROLE_SLUG) {
            return false;
        }

        return $role->portal?->code === PortalCode::RESELLER->value;
    }

    /**
     * Base scoped query for valid Call Center Agents in a company.
     */
    protected function agentQueryForCompany(int $companyId): Builder
    {
        return User::query()
            ->where('users.company_id', $companyId)
            ->where('users.user_type', UserType::EMPLOYEE->value)
            ->whereNull('users.deleted_at')
            ->whereHas('role', function (Builder $roleQuery): void {
                $roleQuery
                    ->where('slug', self::ROLE_SLUG)
                    ->whereNull('deleted_at')
                    ->whereHas('portal', fn (Builder $portalQuery) => $portalQuery->where('code', PortalCode::RESELLER->value));
            });
    }

    protected function resolveManagedAgent(User $actor, User $agent): User
    {
        $this->assertActorHasCompany($actor);

        $managed = $this->agentQueryForCompany((int) $actor->company_id)
            ->where('users.id', $agent->id)
            ->with(['profile', 'role'])
            ->first();

        if ($managed === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$agent->uuid ?? $agent->id]);
        }

        return $managed;
    }

    protected function assertActorCan(User $actor, string $permission): void
    {
        $this->assertActorHasCompany($actor);

        if (! $this->permissionService->hasPermission($actor, $permission)) {
            throw new AuthorizationException(
                'You do not have permission to perform this Call Center Agent action.'
            );
        }
    }

    protected function assertActorHasCompany(User $actor): void
    {
        if ($actor->company_id === null) {
            throw new AuthorizationException(
                'Call Center Agent management requires a reseller company context.'
            );
        }
    }

    protected function assertNotSelf(User $actor, User $agent): void
    {
        if ((int) $actor->id === (int) $agent->id) {
            throw new AuthorizationException(
                'You cannot perform Call Center Agent management actions against your own account.'
            );
        }
    }

    protected function resolveCallCenterAgentRole(): Role
    {
        $role = Role::query()
            ->where('slug', self::ROLE_SLUG)
            ->whereNull('deleted_at')
            ->whereHas('portal', fn (Builder $portalQuery) => $portalQuery->where('code', PortalCode::RESELLER->value))
            ->first();

        if ($role === null) {
            throw ValidationException::withMessages([
                'role' => 'The reseller call-center-agent role could not be resolved.',
            ]);
        }

        return $role;
    }

    /**
     * Established reseller phone-login convention from RegistrationService:
     * {phone}@reseller.local
     */
    protected function generatedResellerEmail(string $phone): string
    {
        return sprintf('%s@reseller.local', $phone);
    }

    protected function assertPhoneAvailable(string $phone, ?int $ignoreUserId = null): void
    {
        $email = $this->generatedResellerEmail($phone);

        $phoneTaken = User::query()
            ->where('phone', $phone)
            ->when($ignoreUserId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreUserId))
            ->exists();

        if ($phoneTaken) {
            throw ValidationException::withMessages([
                'phone' => 'This phone number is already registered.',
            ]);
        }

        $emailTaken = User::query()
            ->where('email', $email)
            ->when($ignoreUserId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreUserId))
            ->exists();

        if ($emailTaken) {
            throw ValidationException::withMessages([
                'phone' => 'This phone number is already registered.',
            ]);
        }
    }

    protected function assertNicAvailable(?string $nic, ?int $ignoreProfileId = null): void
    {
        if ($nic === null) {
            return;
        }

        $taken = UserProfile::query()
            ->where('nic', $nic)
            ->when($ignoreProfileId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreProfileId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'nic' => 'This identity document number is already registered.',
            ]);
        }
    }

    protected function normalizeRequiredString(mixed $value, string $field): string
    {
        if ($value === null) {
            throw ValidationException::withMessages([
                $field => "The {$field} field is required.",
            ]);
        }

        $string = trim((string) $value);

        if ($string === '') {
            throw ValidationException::withMessages([
                $field => "The {$field} field is required.",
            ]);
        }

        return $string;
    }

    protected function normalizeOptionalNic(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * Monetary validation aligned with ResellerServiceChargeService conventions.
     *
     * Rejects more than 2 decimal places instead of silently rounding.
     */
    protected function normalizeCommission(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            throw ValidationException::withMessages([
                'agent_commission_per_order' => 'Agent commission per order is required.',
            ]);
        }

        $string = trim((string) $amount);

        if ($string === '' || ! is_numeric($string)) {
            throw ValidationException::withMessages([
                'agent_commission_per_order' => 'Agent commission per order must be a valid numeric value.',
            ]);
        }

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $string)) {
            throw ValidationException::withMessages([
                'agent_commission_per_order' => 'Agent commission per order must have at most 2 decimal places.',
            ]);
        }

        $value = (float) $string;

        if ($value < 0) {
            throw ValidationException::withMessages([
                'agent_commission_per_order' => 'Agent commission per order cannot be negative.',
            ]);
        }

        if ($value > 100000000) {
            throw ValidationException::withMessages([
                'agent_commission_per_order' => 'Agent commission per order exceeds the supported maximum.',
            ]);
        }

        return number_format($value, 2, '.', '');
    }

    /**
     * @param  array<int|string, bool>  $permissionOverrides
     * @return array<int, bool>
     */
    protected function resolveAssignableOverridePayload(array $permissionOverrides): array
    {
        if ($permissionOverrides === []) {
            return [];
        }

        $payload = [];

        foreach ($permissionOverrides as $identifier => $allowed) {
            $permission = $this->resolveRequestedPermission($identifier);
            $payload[(int) $permission->id] = (bool) $allowed;
        }

        return $payload;
    }

    /**
     * Preview/mock keys are ignored on create. Management and other-portal
     * permissions are rejected. Real RESELLER operational permissions are granted.
     *
     * @param  mixed  $identifiers
     * @return array<int, bool>
     */
    protected function filterCreatePermissionGrants(mixed $identifiers): array
    {
        if (! is_array($identifiers) || $identifiers === []) {
            return [];
        }

        $payload = [];

        foreach ($identifiers as $identifier) {
            if (! is_int($identifier) && ! is_string($identifier)) {
                continue;
            }

            $identifier = is_string($identifier) ? trim($identifier) : $identifier;

            if ($identifier === '' || $identifier === null) {
                continue;
            }

            if (is_string($identifier) && $this->isManagementPermissionSlug($identifier)) {
                throw ValidationException::withMessages([
                    'permissions' => 'Call Center Agent management permissions cannot be assigned to Agents.',
                ]);
            }

            $permission = $this->findPermissionByIdentifier($identifier);

            if ($permission === null) {
                continue;
            }

            $this->assertPermissionMayBeAssignedToAgent($permission);

            $payload[(int) $permission->id] = true;
        }

        return $payload;
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return array<string, array{label: string, description: string, permissions: array<string, string>}>
     */
    protected function buildOperationalCatalog(Collection $permissions): array
    {
        $catalog = [];

        foreach ($permissions as $permission) {
            $groupKey = (string) ($permission->group ?: $permission->module ?: 'general');

            if (! isset($catalog[$groupKey])) {
                $catalog[$groupKey] = [
                    'label' => (string) ($permission->group ?: $permission->module ?: 'General'),
                    'description' => 'Operational permissions available to this call center agent.',
                    'permissions' => [],
                ];
            }

            $catalog[$groupKey]['permissions'][$permission->slug] = $permission->name;
        }

        return $catalog;
    }

    protected function resolveRequestedPermission(int|string $identifier): Permission
    {
        if (is_string($identifier) && $this->isManagementPermissionSlug(trim($identifier))) {
            throw ValidationException::withMessages([
                'permissions' => 'Call Center Agent management permissions cannot be assigned to Agents.',
            ]);
        }

        $permission = $this->findPermissionByIdentifier($identifier);

        if ($permission === null) {
            throw ValidationException::withMessages([
                'permissions' => 'One or more permissions are invalid.',
            ]);
        }

        $this->assertPermissionMayBeAssignedToAgent($permission);

        return $permission;
    }

    protected function findPermissionByIdentifier(int|string $identifier): ?Permission
    {
        if (is_int($identifier) || (is_string($identifier) && ctype_digit($identifier))) {
            return Permission::query()
                ->with('portal')
                ->where('id', (int) $identifier)
                ->first();
        }

        $slug = trim((string) $identifier);

        if ($slug === '') {
            return null;
        }

        return Permission::query()
            ->with('portal')
            ->where('slug', $slug)
            ->whereHas('portal', fn (Builder $portalQuery) => $portalQuery->where('code', PortalCode::RESELLER->value))
            ->first()
            ?? Permission::query()
                ->with('portal')
                ->where('slug', $slug)
                ->first();
    }

    protected function assertPermissionMayBeAssignedToAgent(Permission $permission): void
    {
        if ($this->isManagementPermissionSlug((string) $permission->slug)) {
            throw ValidationException::withMessages([
                'permissions' => 'Call Center Agent management permissions cannot be assigned to Agents.',
            ]);
        }

        if ($this->portalCode($permission) !== PortalCode::RESELLER->value) {
            throw ValidationException::withMessages([
                'permissions' => 'Permissions from another portal cannot be assigned to Call Center Agents.',
            ]);
        }

        if ($permission->trashed()) {
            throw ValidationException::withMessages([
                'permissions' => 'One or more permissions are invalid.',
            ]);
        }

        if (! $this->assignablePermissions()->contains('id', (int) $permission->id)) {
            throw ValidationException::withMessages([
                'permissions' => "Permission [{$permission->slug}] is not assignable to Call Center Agents.",
            ]);
        }
    }

    protected function portalCode(Permission $permission): ?string
    {
        $permission->loadMissing('portal');

        $code = $permission->portal?->code;

        return $code instanceof PortalCode ? $code->value : ($code !== null ? (string) $code : null);
    }

    protected function isManagementPermissionSlug(string $slug): bool
    {
        return str_starts_with($slug, 'call_center.agents.')
            || in_array($slug, self::MANAGEMENT_PERMISSION_SLUGS, true);
    }
}
