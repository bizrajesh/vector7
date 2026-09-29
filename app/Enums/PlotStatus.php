<?php

namespace App\Enums;

enum PlotStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Booked = 'booked';
    case OngoingSale = 'ongoing_sale';
    case Ror = 'ror';
    case OngoingReg = 'ongoing_reg';
    case Sold = 'sold';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Reserved => 'Reserved',
            self::Booked => 'Booked',
            self::OngoingSale => 'Ongoing-Sale',
            self::Ror => 'ROR',
            self::OngoingReg => 'Ongoing-Reg',
            self::Sold => 'Sold',
        };
    }

    /** CSS class suffix used by badges and plot tiles (see resources/css/app.css). */
    public function css(): string
    {
        return match ($this) {
            self::Available => 'av',
            self::Reserved => 'rs',
            self::Booked => 'bk',
            self::OngoingSale => 'os',
            self::Ror => 'ror',
            self::OngoingReg => 'reg',
            self::Sold => 'sold',
        };
    }

    /**
     * The only legal transitions (section 6 status machine). Anything else is refused.
     *
     * @return array<int, self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Available => [self::Booked, self::OngoingSale, self::Ror, self::Reserved],
            self::Reserved => [self::Available],
            self::Booked => [self::Available, self::OngoingSale, self::Ror],
            self::OngoingSale => [self::Ror, self::Available],
            self::Ror => [self::OngoingReg],
            self::OngoingReg => [self::Sold],
            self::Sold => [],
        };
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}
