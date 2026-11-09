<?php

declare(strict_types=1);

use App\Enums\RentFrequency;
use App\Services\Billing\PeriodCalculator;
use Illuminate\Support\Carbon;

function periodDates(string $start, string $end, RentFrequency $frequency, int $anchor): array
{
    return array_map(
        fn (array $p) => $p['start']->toDateString().'/'.$p['end']->toDateString(),
        (new PeriodCalculator)->periods(Carbon::parse($start), Carbon::parse($end), $frequency, $anchor),
    );
}

it('splits a monthly lease into calendar periods on the anchor day', function () {
    expect(periodDates('2026-01-15', '2026-04-14', RentFrequency::Monthly, 15))->toBe([
        '2026-01-15/2026-02-14',
        '2026-02-15/2026-03-14',
        '2026-03-15/2026-04-14',
    ]);
});

it('keeps month ends stable with an anchor of 28', function () {
    expect(periodDates('2026-01-31', '2026-04-27', RentFrequency::Monthly, 28))->toBe([
        '2026-01-31/2026-02-27',
        '2026-02-28/2026-03-27',
        '2026-03-28/2026-04-27',
    ]);
});

it('handles February in a leap year', function () {
    expect(periodDates('2028-01-28', '2028-03-27', RentFrequency::Monthly, 28))->toBe([
        '2028-01-28/2028-02-27',
        '2028-02-28/2028-03-27',
    ]);
});

it('generates quarterly periods', function () {
    expect(periodDates('2026-01-01', '2026-12-31', RentFrequency::Quarterly, 1))->toBe([
        '2026-01-01/2026-03-31',
        '2026-04-01/2026-06-30',
        '2026-07-01/2026-09-30',
        '2026-10-01/2026-12-31',
    ]);
});

it('generates biannual and annual periods across a leap day', function () {
    expect(periodDates('2027-03-01', '2028-02-29', RentFrequency::Biannual, 1))->toBe([
        '2027-03-01/2027-08-31',
        '2027-09-01/2028-02-29',
    ])->and(periodDates('2027-03-01', '2029-02-28', RentFrequency::Annual, 1))->toBe([
        '2027-03-01/2028-02-29',
        '2028-03-01/2029-02-28',
    ]);
});

it('cuts the last period at the lease end date', function () {
    expect(periodDates('2026-01-01', '2026-05-15', RentFrequency::Quarterly, 1))->toBe([
        '2026-01-01/2026-03-31',
        '2026-04-01/2026-05-15',
    ]);
});

it('finds the anchor on or before the start date', function () {
    $calc = new PeriodCalculator;

    expect($calc->anchorOnOrBefore(Carbon::parse('2026-03-10')->toImmutable(), 15)->toDateString())->toBe('2026-02-15')
        ->and($calc->anchorOnOrBefore(Carbon::parse('2026-03-20')->toImmutable(), 15)->toDateString())->toBe('2026-03-15');
});
