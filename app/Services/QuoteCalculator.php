<?php

declare(strict_types=1);

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * All quote arithmetic lives here, in exact decimals (no floats), so totals always add up to the cent.
 *
 *   gross    = quantity x unit price
 *   discount = gross x discount %
 *   net      = gross - discount          (stored as the line total)
 *   tax      = net x tax rate %
 *   total    = sum(gross) - sum(discount) + sum(tax)
 *
 * Every step is rounded half-up to 2 decimals, per line, so the figures on the quote can be re-added by hand.
 */
final class QuoteCalculator
{
    /**
     * @param list<array<string, mixed>> $items
     * @return array{items: list<array<string, mixed>>, totals: array{subtotal: string, discount_total: string, tax_total: string, total: string}}
     */
    public function calculate(array $items): array
    {
        $subtotal = $discountTotal = $taxTotal = BigDecimal::zero()->toScale(2);
        $lines = [];

        foreach (array_values($items) as $position => $item) {
            $gross = BigDecimal::of((string) $item['quantity'])
                ->multipliedBy(BigDecimal::of((string) $item['unit_price']))
                ->toScale(2, RoundingMode::HALF_UP);

            $discount = $gross
                ->multipliedBy(BigDecimal::of((string) ($item['discount_percent'] ?? 0)))
                ->dividedBy(100, 2, RoundingMode::HALF_UP);

            $net = $gross->minus($discount);

            $tax = $net
                ->multipliedBy(BigDecimal::of((string) ($item['tax_rate'] ?? 0)))
                ->dividedBy(100, 2, RoundingMode::HALF_UP);

            $subtotal = $subtotal->plus($gross);
            $discountTotal = $discountTotal->plus($discount);
            $taxTotal = $taxTotal->plus($tax);

            $lines[] = [
                ...$item,
                'position' => $position,
                'discount_percent' => $item['discount_percent'] ?? 0,
                'tax_rate' => $item['tax_rate'] ?? 0,
                'line_total' => (string) $net,
            ];
        }

        return [
            'items' => $lines,
            'totals' => [
                'subtotal' => (string) $subtotal,
                'discount_total' => (string) $discountTotal,
                'tax_total' => (string) $taxTotal,
                'total' => (string) $subtotal->minus($discountTotal)->plus($taxTotal),
            ],
        ];
    }
}
