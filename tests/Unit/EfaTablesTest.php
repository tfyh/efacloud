<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Efa_tables class.
 *
 * Tests static methods and data structures used for efa table handling.
 */
class EfaTablesTest extends TestCase
{
    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Include the Efa_tables class
        require_once __DIR__ . '/../../classes/efa_tables.php';
    }

    /**
     * Test that all efa2 table names have defined data key fields.
     */
    public function testAllEfaTablesHaveDataKeyFields(): void
    {
        $dataKeyFields = \Efa_tables::$efa_data_key_fields;

        $this->assertIsArray($dataKeyFields);
        $this->assertNotEmpty($dataKeyFields);

        // Verify key tables exist
        $expectedTables = [
            'efa2boats',
            'efa2persons',
            'efa2logbook',
            'efa2boatreservations',
            'efa2boatdamages',
            'efaCloudUsers',
        ];

        foreach ($expectedTables as $table) {
            $this->assertArrayHasKey(
                $table,
                $dataKeyFields,
                "Table '$table' should have defined data key fields"
            );
        }
    }

    /**
     * Test that versionized tables have correct key fields (Id + ValidFrom).
     */
    public function testVersionizedTablesHaveCorrectKeyFields(): void
    {
        $versionizedTables = \Efa_tables::$versionized_table_names;
        $dataKeyFields = \Efa_tables::$efa_data_key_fields;

        foreach ($versionizedTables as $table) {
            $this->assertArrayHasKey($table, $dataKeyFields);
            $keys = $dataKeyFields[$table];

            $this->assertContains(
                'Id',
                $keys,
                "Versionized table '$table' should have 'Id' as key field"
            );
            $this->assertContains(
                'ValidFrom',
                $keys,
                "Versionized table '$table' should have 'ValidFrom' as key field"
            );
        }
    }

    /**
     * Test is_efa_table() correctly identifies efa2 tables.
     */
    public function testIsEfaTableIdentifiesEfaTables(): void
    {
        // Tables that should return true
        $this->assertTrue(\Efa_tables::is_efa_table('efa2boats'));
        $this->assertTrue(\Efa_tables::is_efa_table('efa2persons'));
        $this->assertTrue(\Efa_tables::is_efa_table('efa2logbook'));

        // Tables that should return false
        $this->assertFalse(\Efa_tables::is_efa_table('efaCloudUsers'));
        $this->assertFalse(\Efa_tables::is_efa_table('users'));
        $this->assertFalse(\Efa_tables::is_efa_table('some_other_table'));
    }

    /**
     * Test ecrid generation produces valid format.
     */
    public function testGenerateEcridsProducesValidFormat(): void
    {
        $ecrids = \Efa_tables::generate_ecrids(5);

        $this->assertIsArray($ecrids);
        $this->assertCount(5, $ecrids);

        foreach ($ecrids as $ecrid) {
            // ecrid should be 12 characters
            $this->assertEquals(12, strlen($ecrid), "ecrid should be 12 characters");

            // ecrid should be valid according to is_ecrid()
            $this->assertTrue(
                \Efa_tables::is_ecrid($ecrid),
                "Generated ecrid '$ecrid' should be valid"
            );
        }
    }

    /**
     * Test is_ecrid() validates ecrid format correctly.
     */
    public function testIsEcridValidatesFormat(): void
    {
        // Valid ecrids (12 characters from the base64 map)
        $validEcrids = \Efa_tables::generate_ecrids(3);
        foreach ($validEcrids as $ecrid) {
            $this->assertTrue(\Efa_tables::is_ecrid($ecrid));
        }

        // Invalid ecrids
        $this->assertFalse(\Efa_tables::is_ecrid(''));
        $this->assertFalse(\Efa_tables::is_ecrid('short'));
        $this->assertFalse(\Efa_tables::is_ecrid('toolongstring123'));
        $this->assertFalse(\Efa_tables::is_ecrid('invalid!chars'));
    }

    /**
     * Test fix_boolean_text() converts boolean values correctly.
     */
    public function testFixBooleanTextConvertsValues(): void
    {
        // Test conversion from "on" to "true"
        $record = ['Fixed' => 'on', 'Claim' => 'true', 'Description' => 'test'];
        $result = \Efa_tables::fix_boolean_text('efa2boatdamages', $record);

        $this->assertEquals('true', $result['Fixed']);
        $this->assertEquals('on', $result['Claim']);
        $this->assertEquals('test', $result['Description']);
    }

    /**
     * Test get_data_key() extracts correct key fields.
     */
    public function testGetDataKeyExtractsCorrectFields(): void
    {
        // Test single key field table
        $record = ['BoatId' => 'uuid-123', 'Comment' => 'test'];
        $key = \Efa_tables::get_data_key('efa2boatstatus', $record);

        $this->assertIsArray($key);
        $this->assertArrayHasKey('BoatId', $key);
        $this->assertEquals('uuid-123', $key['BoatId']);
        $this->assertArrayNotHasKey('Comment', $key);

        // Test double key field table (logbook)
        $record = ['EntryId' => 1, 'Logbookname' => '2024', 'Date' => '2024-01-01'];
        $key = \Efa_tables::get_data_key('efa2logbook', $record);

        $this->assertIsArray($key);
        $this->assertArrayHasKey('EntryId', $key);
        $this->assertArrayHasKey('Logbookname', $key);
        $this->assertArrayNotHasKey('Date', $key);
    }

    /**
     * Test get_data_key() returns false when key fields are missing.
     */
    public function testGetDataKeyReturnsFalseWhenMissingFields(): void
    {
        // Missing required key field
        $record = ['Comment' => 'test']; // Missing BoatId
        $key = \Efa_tables::get_data_key('efa2boatstatus', $record);

        $this->assertFalse($key);
    }

    /**
     * Test records_are_equal() compares records correctly.
     */
    public function testRecordsAreEqualComparesCorrectly(): void
    {
        $a = ['name' => 'Test', 'value' => '123'];
        $b = ['name' => 'Test', 'value' => '123'];

        $this->assertTrue(\Efa_tables::records_are_equal($a, $b, false));

        // Different values
        $c = ['name' => 'Test', 'value' => '456'];
        $this->assertFalse(\Efa_tables::records_are_equal($a, $c, false));

        // Null and empty string should be equal
        $d = ['name' => 'Test', 'value' => null];
        $e = ['name' => 'Test', 'value' => ''];
        $this->assertTrue(\Efa_tables::records_are_equal($d, $e, false));
    }

    /**
     * Test forever constants are defined correctly.
     */
    public function testForeverConstantsAreDefined(): void
    {
        $this->assertEquals('9223372036854775807', \Efa_tables::$forever64);
        $this->assertEquals(2147483647, \Efa_tables::$forever32int);
        $this->assertEquals(24855, \Efa_tables::$forever_days);
    }

    /**
     * Test UUID field names are defined.
     */
    public function testUuidFieldNamesAreDefined(): void
    {
        $uuidFields = \Efa_tables::$UUID_field_names;

        $this->assertIsArray($uuidFields);
        $this->assertContains('BoatId', $uuidFields);
        $this->assertContains('PersonId', $uuidFields);
        $this->assertContains('CoxId', $uuidFields);
        $this->assertContains('DestinationId', $uuidFields);
    }
}
