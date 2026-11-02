<?php

namespace Tests\Unit\Settlement;

use App\Services\Settlement\ResellerSettlementInvoiceReadService;
use Feeder\Core\Models\User;
use Tests\TestCase;

/**
 * Ownership of the reseller financial overview query.
 *
 * Builds SQL only. Uses the phpunit sqlite memory connection and does not
 * open the shared dropshipping database.
 */
class ResellerOwnedInvoiceQueryTest extends TestCase
{
    public function test_owned_invoice_query_is_limited_to_the_actor(): void
    {
        $actor = new User();
        $actor->id = 42;

        $query = app(ResellerSettlementInvoiceReadService::class)
            ->ownedInvoices($actor, '2026-10-01', '2026-10-31');

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('reseller_id', $sql);
        $this->assertStringContainsString('voided_at', $sql);
        $this->assertStringContainsString('settlement_date', $sql);
        $this->assertSame(42, $bindings[0]);
        $this->assertContains('2026-10-01', $bindings);
        $this->assertContains('2026-10-31', $bindings);
        $this->assertNotContains(99, $bindings);
    }

    public function test_owned_invoice_query_without_dates_does_not_add_another_reseller(): void
    {
        $actor = new User();
        $actor->id = 7;

        $query = app(ResellerSettlementInvoiceReadService::class)->ownedInvoices($actor);

        $this->assertStringContainsString('reseller_id', $query->toSql());
        $this->assertSame([7], $query->getBindings());
    }
}
