<?php

namespace Tests\Feature;

use App\Support\Excel;
use Tests\TestCase;

/** Uploaded spreadsheets whose cells are formulas are read by their calculated values (e.g. a rate looked up from a rates tab). */
class ExcelFormulaImportTest extends TestCase
{
    public function test_formula_cells_are_read_as_computed_values(): void
    {
        $t = Excel::read(base_path('tests/fixtures/formula-plots.xlsx'), 'xlsx');
        $this->assertSame(['plot_no', 'size_sqft', 'rate_per_sqft', 'status'], $t['headers']);
        $this->assertEquals(950, $t['rows'][0]['rate_per_sqft']);
        $this->assertSame('Available', $t['rows'][0]['status']);
    }
}
