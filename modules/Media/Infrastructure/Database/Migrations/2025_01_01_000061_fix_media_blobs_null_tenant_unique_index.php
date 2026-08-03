<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
 * MySQL 8: Use a functional index that coalesces NULL to a sentinel UUID.
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
        } elseif ($driver === 'mysql') {
            // MySQL 8 supports functional indexes. This avoids a generated
            // column, which MySQL cannot add when it derives from a column
            // participating in an ON DELETE CASCADE foreign key.
            // Keep a dedicated index for the tenant foreign key before removing
            // the composite one MySQL initially selected to enforce it.
            DB::statement('CREATE INDEX media_blobs_tenant_id_index ON media_blobs (tenant_id)');
            DB::statement('ALTER TABLE media_blobs DROP INDEX media_blobs_tenant_id_sha256_unique');
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX media_blobs_tenant_sha256_unique
                ON media_blobs ((COALESCE(tenant_id, '00000000-0000-0000-0000-000000000000')), sha256)
            SQL);
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
        } elseif ($driver === 'mysql') {
            DB::statement('DROP INDEX media_blobs_tenant_sha256_unique ON media_blobs');
            DB::statement('CREATE UNIQUE INDEX media_blobs_tenant_id_sha256_unique ON media_blobs (tenant_id, sha256)');
            DB::statement('DROP INDEX media_blobs_tenant_id_index ON media_blobs');
        }
    }
};
