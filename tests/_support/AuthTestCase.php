<?php

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\Seeds\TestAuthSeeder;

abstract class AuthTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = false;
    protected $migrateOnce = false;
    protected $refresh = false;
    protected $seedOnce = false;
    protected $seed = TestAuthSeeder::class;
    protected $DBGroup = 'tests';

    /**
     * Mandatory 5-color corporate palette defined in Requirement R4
     */
    protected const PALETTE = [
        '#BF0426',
        '#0511F2',
        '#023373',
        '#A61717',
        '#8D253C',
    ];

    /**
     * Helper to assert all corporate colors are present in an HTML view.
     * Checks case-insensitively to accommodate lowercase or uppercase hex definitions.
     */
    protected function assertCorporatePalettePresent(string $html): void
    {
        foreach (self::PALETTE as $color) {
            $this->assertStringContainsStringIgnoringCase(
                $color,
                $html,
                "Corporate palette color {$color} was not found in response HTML."
            );
        }
    }
}
