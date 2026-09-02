<?php
namespace Qnox\Workflows\Tests;
use Illuminate\Support\Facades\Schema;
class MigrationTest extends TestCase
{
    public function test_fresh_schema_contains_only_v2_workflow_tables(): void
    {
        foreach(['workflows','workflow_levels','workflow_level_users','workflow_instances','workflow_instance_levels','workflow_instance_approvers','workflow_actions','workflow_inbox_items'] as $table)$this->assertTrue(Schema::hasTable($table));
        foreach(['workflow_groups','workflow_modules','workflow_assignments','workflow_transitions','workflow_level_participants','workflow_number_sequences'] as $table)$this->assertFalse(Schema::hasTable($table));
    }
}
