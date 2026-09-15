<?php

declare(strict_types=1);

namespace CoreX\Money;

use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts a pair of columns (minor amount + currency) to a Brick\Money\Money
 * value object — money is stored in minor units, never as float (ADR-005).
 *
 * Usage: protected $casts = ['price' => MoneyCast::class.':price_minor,currency'];
 *
 * @implements CastsAttributes<Money|null, mixed>
 */
final class MoneyCast implements CastsAttributes
{
    public function __construct(
        private readonly string $amountColumn = 'amount_minor',
        private readonly string $currencyColumn = 'currency',
    ) {}

    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        $minor = $attributes[$this->amountColumn] ?? null;
        $currency = $attributes[$this->currencyColumn] ?? null;

        if ($minor === null || $currency === null) {
            return null;
        }

        return Money::ofMinor((int) $minor, (string) $currency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$this->amountColumn => null, $this->currencyColumn => null];
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException('MoneyCast expects a '.Money::class.' instance.');
        }

        return [
            $this->amountColumn => $value->getMinorAmount()->toInt(),
            $this->currencyColumn => $value->getCurrency()->getCurrencyCode(),
        ];
    }
}
