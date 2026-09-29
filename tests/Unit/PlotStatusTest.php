<?php

namespace Tests\Unit;

use App\Enums\PlotStatus;
use PHPUnit\Framework\TestCase;

class PlotStatusTest extends TestCase
{
    public function test_only_documented_transitions_are_allowed(): void
    {
        $this->assertTrue(PlotStatus::Available->canMoveTo(PlotStatus::Booked));
        $this->assertTrue(PlotStatus::Booked->canMoveTo(PlotStatus::Available)); // booking expiry
        $this->assertTrue(PlotStatus::Booked->canMoveTo(PlotStatus::OngoingSale));
        $this->assertTrue(PlotStatus::OngoingSale->canMoveTo(PlotStatus::Ror));
        $this->assertTrue(PlotStatus::Ror->canMoveTo(PlotStatus::OngoingReg));
        $this->assertTrue(PlotStatus::OngoingReg->canMoveTo(PlotStatus::Sold));

        $this->assertFalse(PlotStatus::Available->canMoveTo(PlotStatus::Sold));
        $this->assertFalse(PlotStatus::Booked->canMoveTo(PlotStatus::Sold));
        $this->assertFalse(PlotStatus::Ror->canMoveTo(PlotStatus::Available));
        $this->assertSame([], PlotStatus::Sold->allowedNext());
    }
}
