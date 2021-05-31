<?php

declare(strict_types=1);

namespace App\Services;

class CurrenciesService
{
    protected const CURRENCIES_CACHE_KEY = 'currencies_list_json';
    protected const CURRENCIES_CACHE_TTL = 3600;
    protected const SCALE_FLOAT = 2;

    private $currencies = [];

    public function convert(float $amount, string $fromCurrency, string $toCurrency = 'USD'): string
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);
        if ($amount <= 0 || $fromCurrency === $toCurrency) {
            return (string) $amount;
        }

        $currencies = $this->getCurrencies()['Valute'];
        if ($toCurrency === 'RUB') {
            return bcmul((string)$amount, (string)$currencies[$fromCurrency]['Value'], self::SCALE_FLOAT);
        }

        return bcmul((string)$amount, (string)($currencies[$fromCurrency]['Value'] / $currencies[$toCurrency]['Value']), self::SCALE_FLOAT);
    }

    protected function getCurrencies(): array
    {
        if ($this->currencies) {
            return $this->currencies;
        }

        try {
            $currencies = \Cache::get(self::CURRENCIES_CACHE_KEY);
            if (empty($currencies)) {
                $currencies = json_decode(
                    file_get_contents('https://www.cbr-xml-daily.ru/daily_json.js'),
                    true,
                    512,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                );
                if (!empty($currencies)) {
                    \Cache::put(self::CURRENCIES_CACHE_KEY, $currencies, self::CURRENCIES_CACHE_TTL);
                }
            }
        } catch (\Throwable $e) {
            logger('Exception on get currencies: ' . $e->getMessage());
            throw $e;
        }

        return $this->currencies = $currencies;
    }
}
