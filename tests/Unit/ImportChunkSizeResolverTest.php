<?php

namespace Tests\Unit;

use App\Services\Import\ImportChunkSizeResolver;
use Tests\TestCase;

class ImportChunkSizeResolverTest extends TestCase
{
    public function test_small_import_uses_single_chunk(): void
    {
        config([
            'import.single_job_max_rows' => 500,
            'import.chunk_size' => 500,
        ]);

        $this->assertSame(120, ImportChunkSizeResolver::forRowCount(120, 'import.chunk_size', 500));
    }

    public function test_large_import_uses_configured_chunk_size(): void
    {
        config([
            'import.single_job_max_rows' => 500,
            'import.chunk_size' => 500,
        ]);

        $this->assertSame(500, ImportChunkSizeResolver::forRowCount(1200, 'import.chunk_size', 500));
    }
}
