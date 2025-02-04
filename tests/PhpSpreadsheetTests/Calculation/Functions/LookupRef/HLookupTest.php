<?php

namespace PhpOffice\PhpSpreadsheetTests\Calculation\Functions\LookupRef;

use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use PhpOffice\PhpSpreadsheet\Calculation\LookupRef;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;

class HLookupTest extends TestCase
{
    /**
     * @dataProvider providerHLOOKUP
     *
     * @param mixed $expectedResult
     * @param mixed $lookup
     * @param mixed $rowIndex
     */
    public function testHLOOKUP($expectedResult, $lookup, array $values, $rowIndex, ?bool $rangeLookup = null): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $maxRow = 0;
        $maxCol = 0;
        $maxColLetter = 'A';
        $row = 0;
        foreach ($values as $rowValues) {
            ++$row;
            ++$maxRow;
            $col = 0;
            if (!is_array($rowValues)) {
                $rowValues = [$rowValues];
            }
            foreach ($rowValues as $cellValue) {
                ++$col;
                $colLetter = Coordinate::stringFromColumnIndex($col);
                if ($col > $maxCol) {
                    $maxCol = $col;
                    $maxColLetter = $colLetter;
                }
                if ($cellValue !== null) {
                    $sheet->getCell("$colLetter$row")->setValue($cellValue);
                }
            }
        }

        $boolArg = self::parseRangeLookup($rangeLookup);
        $sheet->getCell('ZZ8')->setValue($lookup);
        $sheet->getCell('ZZ7')->setValue($rowIndex);
        $sheet->getCell('ZZ1')->setValue("=HLOOKUP(ZZ8, A1:$maxColLetter$maxRow, ZZ7$boolArg)");
        self::assertEquals($expectedResult, $sheet->getCell('ZZ1')->getCalculatedValue());

        $spreadsheet->disconnectWorksheets();
    }

    private static function parseRangeLookup(?bool $rangeLookup): string
    {
        if ($rangeLookup === null) {
            return '';
        }

        return $rangeLookup ? ', true' : ', false';
    }

    public function providerHLOOKUP(): array
    {
        return require 'tests/data/Calculation/LookupRef/HLOOKUP.php';
    }

    public function testGrandfathered(): void
    {
        // Second parameter is supposed to be array of arrays.
        // Some old tests called function directly using array of strings;
        // ensure these work as before.
        $expectedResult = '#REF!';
        $result = /** @scrutinizer ignore-deprecated */ LookupRef::HLOOKUP(
            'Selection column',
            ['Selection column', 'Value to retrieve'],
            5,
            false
        );
        self::assertSame($expectedResult, $result);
        $expectedResult = 'Value to retrieve';
        $result = /** @scrutinizer ignore-deprecated */ LookupRef::HLOOKUP(
            'Selection column',
            ['Selection column', 'Value to retrieve'],
            2,
            false
        );
        self::assertSame($expectedResult, $result);
    }

    /**
     * @dataProvider providerHLookupNamedRange
     */
    public function testHLookupNamedRange(string $expectedResult, string $cellAddress): void
    {
        $lookupData = [
            ['Rating', 1, 2, 3, 4],
            ['Level', 'Poor', 'Average', 'Good', 'Excellent'],
        ];
        $formData = [
            ['Category', 'Rating', 'Level'],
            ['Service', 2, '=HLOOKUP(C5,Lookup_Table,2,FALSE)'],
            ['Quality', 3, '=HLOOKUP(C6,Lookup_Table,2,FALSE)'],
            ['Value', 4, '=HLOOKUP(C7,Lookup_Table,2,FALSE)'],
            ['Cleanliness', 3, '=HLOOKUP(C8,Lookup_Table,2,FALSE)'],
        ];

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->fromArray($lookupData, null, 'F4');
        $worksheet->fromArray($formData, null, 'B4');

        $spreadsheet->addNamedRange(new NamedRange('Lookup_Table', $worksheet, '=$G$4:$J$5'));

        $result = $worksheet->getCell($cellAddress)->getCalculatedValue();
        self::assertEquals($expectedResult, $result);
    }

    public function providerHLookupNamedRange(): array
    {
        return [
            ['Average', 'D5'],
            ['Good', 'D6'],
            ['Excellent', 'D7'],
            ['Good', 'D8'],
        ];
    }

    /**
     * @dataProvider providerHLookupArray
     */
    public function testHLookupArray(array $expectedResult, string $values, string $database, string $index): void
    {
        $calculation = Calculation::getInstance();

        $formula = "=HLOOKUP({$values}, {$database}, {$index}, false)";
        $result = $calculation->_calculateFormulaValue($formula);
        self::assertEquals($expectedResult, $result);
    }

    public function providerHLookupArray(): array
    {
        return [
            'row vector #1' => [
                [[4, 9]],
                '{"Axles", "Bolts"}',
                '{"Axles", "Bearings", "Bolts"; 4, 4, 9; 5, 7, 10; 6, 8, 11}',
                '2',
            ],
            'row vector #2' => [
                [[5, 7]],
                '{"Axles", "Bearings"}',
                '{"Axles", "Bearings", "Bolts"; 4, 4, 9; 5, 7, 10; 6, 8, 11}',
                '3',
            ],
            'row/column vectors' => [
                [[4, 9], [5, 10]],
                '{"Axles", "Bolts"}',
                '{"Axles", "Bearings", "Bolts"; 4, 4, 9; 5, 7, 10; 6, 8, 11}',
                '{2; 3}',
            ],
        ];
    }
}
