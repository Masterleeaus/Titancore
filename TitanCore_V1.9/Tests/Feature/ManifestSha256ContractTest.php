<?php

namespace Modules\TitanCore\Tests\Feature;

use Tests\TestCase;

/**
 * Contract test: titan:verify-manifest must pass on a clean checkout.
 *
 * This test ensures the MANIFEST.sha256 in the repository is consistent with
 * the actual files on disk. If a developer modifies TitanCore files without
 * regenerating MANIFEST.sha256, this test will fail, matching the CI behaviour.
 *
 * To fix: run `php artisan titan:generate-manifest` and commit the result.
 */
class ManifestSha256ContractTest extends TestCase
{
    /** @test */
    public function verify_manifest_command_passes_on_clean_checkout(): void
    {
        $manifestFile = base_path('Modules/TitanCore/MANIFEST.sha256');

        if (! is_file($manifestFile)) {
            $this->markTestSkipped('MANIFEST.sha256 not found. Run titan:generate-manifest first.');
        }

        $exitCode = $this->artisan('titan:verify-manifest')->run();

        $this->assertEquals(
            0,
            $exitCode,
            'MANIFEST.sha256 does not match current files. '
            .'Run `php artisan titan:generate-manifest` and commit the updated MANIFEST.sha256.'
        );
    }
}
