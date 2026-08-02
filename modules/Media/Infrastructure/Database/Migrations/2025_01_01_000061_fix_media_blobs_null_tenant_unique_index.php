<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the simple UNIQUE(tenant_id, sha256) index on media_blobs with a
 * partial-index-based equivalent that handles NULL tenant_id correctly.
 *
 * Problem: In standard SQL, NULL ≠ NULL inside a UNIQUE index, so two rows
 * with tenant_id = NULL and the same sha256 are not treated as duplicates —
 * defeating the deduplication logic entirely.
 *
 * PostgreSQL fix: use COALESCE to substitute NULL with a sentinel string so
 * NULL rows are treated as belonging to a single shared "GLOBAL" namespace.
 *
 * MySQL/MariaDB: Use a generated virtual column that coalesces NULL to 'GLOBAL',
 * then index on (tenant_id_coalesced, sha256).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Drop the standard unique index added in the original migration.
            DB::statement('DROP INDEX IF EXISTS media_blobs_tenant_id_sha256_unique');

            // Create a functional unique index that treats NULL tenant_id as 'GLOBAL'.
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX media_blobs_tenant_sha256_unique
                ON media_blobs (COALESCE(tenant_id::text, 'GLOBAL'), sha256)
                WHERE deleted_at IS NULL
            SQL);
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            // MySQL doesn't support functional indexes in older versions;
            // use a generated column approach instead.
            Schema::table('media_blobs', function ($table) {
                // Add a generated column that is never NULL.
                DB::statement(
                    "ALTER TABLE media_blobs ADD COLUMN tenant_id_coalesced VARCHAR(36) GENERATED ALWAYS AS (COALESCE(tenant_id, 'GLOBAL')) STORED"
                );
            });

            // Drop old index, add new one on the generated column.
            DB::statement('ALTER TABLE media_blobs DROP INDEX media_blobs_tenant_id_sha256_unique');
            DB::statement('CREATE UNIQUE INDEX media_blobs_tenant_sha256_unique ON media_blobs (tenant_id_coalesced, sha256)');
        }
        // SQLite (testing): the original index is sufficient for tests since
        // tenant_id is always provided in the test environment.
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS media_blobs_tenant_sha256_unique');
            DB::statement('CREATE UNIQUE INDEX media_blobs_tenant_id_sha256_unique ON media_blobs (tenant_id, sha256)');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('DROP INDEX media_blobs_tenant_sha256_unique ON media_blobs');
            DB::statement('ALTER TABLE media_blobs DROP COLUMN tenant_id_coalesced');
            DB::statement('CREATE UNIQUE INDEX media_blobs_tenant_id_sha256_unique ON media_blobs (tenant_id, sha256)');
        }
    }
};
