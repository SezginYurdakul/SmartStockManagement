<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'symbol_first' => true,
                'is_active' => true,
            ],
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'decimal_places' => 2,
                'thousands_separator' => '.',
                'decimal_separator' => ',',
                'symbol_first' => false,
                'is_active' => true,
            ],
            [
                'code' => 'TRY',
                'name' => 'Turkish Lira',
                'symbol' => '₺',
                'decimal_places' => 2,
                'thousands_separator' => '.',
                'decimal_separator' => ',',
                'symbol_first' => true,
                'is_active' => true,
            ],
            [
                'code' => 'GBP',
                'name' => 'British Pound',
                'symbol' => '£',
                'decimal_places' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'symbol_first' => true,
                'is_active' => true,
            ],
            [
                'code' => 'JPY',
                'name' => 'Japanese Yen',
                'symbol' => '¥',
                'decimal_places' => 0,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'symbol_first' => true,
                'is_active' => false,
            ],
            [
                'code' => 'CNY',
                'name' => 'Chinese Yuan',
                'symbol' => '¥',
                'decimal_places' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'symbol_first' => true,
                'is_active' => false,
            ],
        ];

        foreach ($currencies as $currencyData) {
            Currency::firstOrCreate(
                ['code' => $currencyData['code']],
                $currencyData
            );
        }

        $this->command->info('Currencies seeded: ' . count($currencies) . ' currencies');

        // Create exchange rates (base: USD)
        $exchangeRates = [
            ['from' => 'USD', 'to' => 'EUR', 'rate' => 0.92],
            ['from' => 'USD', 'to' => 'TRY', 'rate' => 34.50],
            ['from' => 'USD', 'to' => 'GBP', 'rate' => 0.79],
            ['from' => 'USD', 'to' => 'JPY', 'rate' => 157.00],
            ['from' => 'USD', 'to' => 'CNY', 'rate' => 7.24],
            ['from' => 'EUR', 'to' => 'USD', 'rate' => 1.09],
            ['from' => 'EUR', 'to' => 'TRY', 'rate' => 37.50],
            ['from' => 'EUR', 'to' => 'GBP', 'rate' => 0.86],
            ['from' => 'TRY', 'to' => 'USD', 'rate' => 0.029],
            ['from' => 'TRY', 'to' => 'EUR', 'rate' => 0.027],
            ['from' => 'GBP', 'to' => 'USD', 'rate' => 1.27],
            ['from' => 'GBP', 'to' => 'EUR', 'rate' => 1.16],
        ];

        foreach ($exchangeRates as $rateData) {
            ExchangeRate::firstOrCreate(
                [
                    'from_currency' => $rateData['from'],
                    'to_currency' => $rateData['to'],
                    'effective_date' => now()->toDateString(),
                ],
                [
                    'rate' => $rateData['rate'],
                    'source' => 'manual',
                ]
            );
        }

        $this->command->info('Exchange rates seeded: ' . count($exchangeRates) . ' rates');
    }
}
