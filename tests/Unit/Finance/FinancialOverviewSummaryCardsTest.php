<?php

namespace Tests\Unit\Finance;

use Feeder\Core\Models\Currency;
use Feeder\Core\Models\Market;
use Tests\TestCase;

class FinancialOverviewSummaryCardsTest extends TestCase
{
    public function test_reseller_cards_follow_the_dashboard_icon_and_one_currency_per_market(): void
    {
        $dashboard = (string) file_get_contents(resource_path('views/pages/main/dashboard.blade.php'));
        $icon = 'width: 75px; height: 75px; line-height: 105px; background-color: #EF4923;';

        $this->assertStringContainsString($icon, $dashboard);
        $this->assertStringContainsString('style="color: #FFFFFF;"', $dashboard);

        $html = view('pages.finance.partials.market-summary', [
            'overviewMarkets' => collect([
                $this->market('lk', 'Sri Lanka', 'LKR'),
                $this->market('my', 'Malaysia', 'MYR'),
            ]),
            'cards' => $this->cards(),
        ])->render();

        $this->assertStringContainsString($icon, $html);
        $this->assertStringContainsString('style="color: #FFFFFF;"', $html);
        $this->assertStringContainsString('payments', $html);
        $this->assertStringContainsString('receipt_long', $html);
        $this->assertStringContainsString('paid', $html);
        $this->assertStringContainsString('account_balance', $html);
        $this->assertStringNotContainsString('theme-basic', $html);
        $this->assertSame(8, substr_count($html, 'finance-today-card'));
        $this->assertSame(8, substr_count($html, 'Pending integration'));

        $sriLanka = $this->section($html, 'financial-overview-market-lk');
        $malaysia = $this->section($html, 'financial-overview-market-my');

        $this->assertStringNotContainsString('MYR', $sriLanka);
        $this->assertStringContainsString('LKR', $sriLanka);
        $this->assertStringNotContainsString('LKR', $malaysia);
        $this->assertStringContainsString('MYR', $malaysia);
        $this->assertSame(4, substr_count($sriLanka, 'finance-today-card'));
        $this->assertSame(4, substr_count($malaysia, 'finance-today-card'));
    }

    public function test_reseller_cards_cover_one_market_or_none(): void
    {
        $malaysia = view('pages.finance.partials.market-summary', [
            'overviewMarkets' => collect([$this->market('my', 'Malaysia', 'MYR')]),
            'cards' => $this->cards(),
        ])->render();

        $this->assertStringContainsString('id="financial-overview-market-my"', $malaysia);
        $this->assertStringNotContainsString('id="financial-overview-market-lk"', $malaysia);
        $this->assertSame(4, substr_count($malaysia, 'finance-today-card'));
        $this->assertStringNotContainsString('LKR', $malaysia);

        $empty = view('pages.finance.partials.market-summary', [
            'overviewMarkets' => collect(),
            'cards' => $this->cards(),
        ])->render();

        $this->assertStringContainsString('financialOverviewMarketsEmpty', $empty);
        $this->assertStringContainsString('No eligible Sri Lanka or Malaysia market is available for your account.', $empty);
        $this->assertStringNotContainsString('finance-today-card', $empty);
    }

    private function market(string $code, string $name, string $currency): Market
    {
        $market = new Market([
            'code' => $code,
            'name' => $name,
            'is_active' => true,
        ]);
        $market->setRelation('currency', new Currency(['iso_code' => $currency]));

        return $market;
    }

    /**
     * @return list<array{id: string, title: string, icon: string, caption: string, foot: string}>
     */
    private function cards(): array
    {
        return [
            ['id' => 'reseller-total-earnings-card', 'title' => 'Total earnings', 'icon' => 'payments', 'caption' => 'Earnings', 'foot' => 'Your account'],
            ['id' => 'reseller-total-deductions-card', 'title' => 'Total deductions', 'icon' => 'receipt_long', 'caption' => 'Deductions', 'foot' => 'By currency'],
            ['id' => 'reseller-total-paid-card', 'title' => 'Total paid', 'icon' => 'paid', 'caption' => 'Paid', 'foot' => 'Cash payments only'],
            ['id' => 'reseller-outstanding-card', 'title' => 'Outstanding balance', 'icon' => 'account_balance', 'caption' => 'Outstanding', 'foot' => 'By currency'],
        ];
    }

    private function section(string $html, string $id): string
    {
        $start = strpos($html, 'id="'.$id.'"');
        $this->assertNotFalse($start);

        $next = strpos($html, '<section', $start + 1);

        return $next === false ? substr($html, $start) : substr($html, $start, $next - $start);
    }
}
