<?php

namespace Kreatif\CodiceFiscale\Tests\Unit;

use Kreatif\CodiceFiscale\Parsers\RstGridTableParser;
use Kreatif\CodiceFiscale\Tests\TestCase;

class RstGridTableParserTest extends TestCase
{
    public function test_it_parses_rst_grid_tables_with_nested_attributes()
    {
        // The parser expects attributes to be in the form of " - KEY: Value" (with a space before the dash)
        // Let's check the code: if (!is_string($value) || !str_contains($value, "\n - "))
        $rstContent = <<<RST
+------------+------------+------------------------+
| CODAT      | DENOM      | ATTRIBUTES             |
+============+============+========================+
| H501       | ROMA       | ROMA                   |
|            |            |  - PROV: RM            |
|            |            |  - REG: LAZIO          |
+------------+------------+------------------------+
| A952       | BOLZANO    | BOLZANO                |
|            |            |  - PROV: BZ            |
|            |            |  - DE: BOZEN           |
+------------+------------+------------------------+
RST;

        $parser = new RstGridTableParser();
        $parser->parseContent($rstContent);

        $this->assertCount(2, $parser->rows);
        
        $roma = $parser->rows[0];
        $this->assertEquals('H501', $roma['CODAT']);
        $this->assertEquals('ROMA', $roma['DENOM']);
        // Verify expansion of nested attributes from the 'ATTRIBUTES' column
        // The parser keeps the column name and adds subkeys to the row
        $this->assertEquals('RM', $roma['PROV']);
        $this->assertEquals('LAZIO', $roma['REG']);

        $bolzano = $parser->rows[1];
        $this->assertEquals('A952', $bolzano['CODAT']);
        $this->assertEquals('BZ', $bolzano['PROV']);
        $this->assertEquals('BOZEN', $bolzano['DE']);
    }
}
