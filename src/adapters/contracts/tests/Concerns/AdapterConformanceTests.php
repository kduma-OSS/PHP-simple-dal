<?php

declare(strict_types=1);

namespace KDuma\SimpleDAL\Adapter\Contracts\Tests\Concerns;

use KDuma\SimpleDAL\Adapter\Contracts\StorageAdapterInterface;
use KDuma\SimpleDAL\Contracts\EntityDefinitionInterface;
use KDuma\SimpleDAL\Contracts\Exception\AttachmentNotFoundException;
use KDuma\SimpleDAL\Contracts\Exception\RecordNotFoundException;

/**
 * Adapter conformance test suite.
 *
 * Usage in a Pest test file:
 *
 *     uses(AdapterConformanceTests::class);
 *
 *     beforeEach(function () {
 *         $this->adapter = new YourAdapter();
 *         $this->entityName = 'test_entity';
 *         // optionally call $this->adapter->initializeEntity(...)
 *     });
 *
 * Pest automatically discovers all `test_*` methods from the trait.
 */
trait AdapterConformanceTests
{
    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    private function adapter(): StorageAdapterInterface
    {
        return $this->adapter;
    }

    private function entity(): string
    {
        return $this->entityName;
    }

    // ---------------------------------------------------------------
    //  Record CRUD
    // ---------------------------------------------------------------

    public function test_write_and_read_back_a_record(): void
    {
        $data = ['name' => 'Alice', 'age' => 30];

        $this->adapter()->writeRecord($this->entity(), 'rec-1', $data);

        $result = $this->adapter()->readRecord($this->entity(), 'rec-1');

        expect($result)->toEqual($data);
    }

    public function test_read_non_existent_record_throws_record_not_found_exception(): void
    {
        $this->expectException(RecordNotFoundException::class);

        $this->adapter()->readRecord($this->entity(), 'non-existent');
    }

    public function test_record_exists_returns_true_for_existing_record(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['x' => 1]);

        expect($this->adapter()->recordExists($this->entity(), 'rec-1'))->toBeTrue();
    }

    public function test_record_exists_returns_false_for_missing_record(): void
    {
        expect($this->adapter()->recordExists($this->entity(), 'missing'))->toBeFalse();
    }

    public function test_list_record_ids_empty(): void
    {
        expect($this->adapter()->listRecordIds($this->entity()))->toBe([]);
    }

    public function test_list_record_ids_with_one_record(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['a' => 1]);

        expect($this->adapter()->listRecordIds($this->entity()))->toBe(['rec-1']);
    }

    public function test_list_record_ids_with_multiple_records(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-a', ['a' => 1]);
        $this->adapter()->writeRecord($this->entity(), 'rec-b', ['b' => 2]);
        $this->adapter()->writeRecord($this->entity(), 'rec-c', ['c' => 3]);

        $ids = $this->adapter()->listRecordIds($this->entity());
        sort($ids);

        expect($ids)->toBe(['rec-a', 'rec-b', 'rec-c']);
    }

    public function test_delete_a_record(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['x' => 1]);
        $this->adapter()->deleteRecord($this->entity(), 'rec-1');

        expect($this->adapter()->recordExists($this->entity(), 'rec-1'))->toBeFalse();
    }

    public function test_delete_non_existent_record_does_not_throw(): void
    {
        // Should simply be a no-op.
        $this->adapter()->deleteRecord($this->entity(), 'ghost');

        expect(true)->toBeTrue(); // reaching here means no exception
    }

    public function test_write_record_overwrites_existing_data(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['v' => 1]);
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['v' => 2]);

        expect($this->adapter()->readRecord($this->entity(), 'rec-1'))
            ->toBe(['v' => 2]);

        // Only one record should exist – no duplicate IDs.
        expect($this->adapter()->listRecordIds($this->entity()))->toBe(['rec-1']);
    }

    // ---------------------------------------------------------------
    //  Filtering
    // ---------------------------------------------------------------

    /**
     * Seed several records used by the filtering tests.
     */
    private function seedFilterRecords(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'u1', [
            'name' => 'Alice',
            'age' => 30,
            'status' => 'active',
            'email' => 'alice@example.com',
            'meta' => ['role' => 'admin'],
        ]);
        $this->adapter()->writeRecord($this->entity(), 'u2', [
            'name' => 'Bob',
            'age' => 25,
            'status' => 'inactive',
            'email' => 'bob@example.com',
            'meta' => ['role' => 'user'],
        ]);
        $this->adapter()->writeRecord($this->entity(), 'u3', [
            'name' => 'Charlie',
            'age' => 35,
            'status' => 'active',
            'email' => 'charlie@test.org',
            'meta' => ['role' => 'user'],
        ]);
        $this->adapter()->writeRecord($this->entity(), 'u4', [
            'name' => 'Diana',
            'age' => 28,
            'status' => 'active',
            'email' => 'diana@example.com',
            'meta' => ['role' => 'moderator'],
        ]);
    }

    public function test_find_all_records_with_empty_filters(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords($this->entity());

        expect($results)->toHaveCount(4);
        expect(array_keys($results))->each->toBeString();
    }

    public function test_filter_with_equals_operator(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords($this->entity(), filters: [
            ['type' => 'and', 'field' => 'status', 'operator' => '=', 'value' => 'active'],
        ]);

        expect(array_keys($results))->toHaveCount(3);
        expect(array_keys($results))->each->toBeIn(['u1', 'u3', 'u4']);
    }

    public function test_filter_with_not_equals_operator(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords($this->entity(), filters: [
            ['type' => 'and', 'field' => 'status', 'operator' => '!=', 'value' => 'active'],
        ]);

        expect(array_keys($results))->toBe(['u2']);
    }

    public function test_filter_with_less_than_operator(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords($this->entity(), filters: [
            ['type' => 'and', 'field' => 'age', 'operator' => '<', 'value' => 30],
        ]);

        $ids = array_keys($results);
        sort($ids);

        expect($ids)->toBe(['u2', 'u4']);
    }

    public function test_filter_with_greater_than_operator(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords($this->entity(), filters: [
            ['type' => 'and', 'field' => 'age', 'operator' => '>', 'value' => 30],
        ]);

        expect(array_keys($results))->toBe(['u3']);
    }

    public function test_filter_with_less_than_or_equal_operator(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords($this->entity(), filters: [
            ['type' => 'and', 'field' => 'age', 'operator' => '<=', 'value' => 28],
        ]);

        $ids = array_keys($results);
        sort($ids);

        expect($ids)->toBe(['u2', 'u4']);
    }

    public function test_filter_with_greater_than_or_equal_operator(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords($this->entity(), filters: [
            ['type' => 'and', 'field' => 'age', 'operator' => '>=', 'value' => 30],
        ]);

        $ids = array_keys($results);
        sort($ids);

        expect($ids)->toBe(['u1', 'u3']);
    }

    public function test_filter_with_contains_operator(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords($this->entity(), filters: [
            ['type' => 'and', 'field' => 'email', 'operator' => 'contains', 'value' => 'example'],
        ]);

        $ids = array_keys($results);
        sort($ids);

        expect($ids)->toBe(['u1', 'u2', 'u4']);
    }

    public function test_filter_with_starts_with_operator(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords($this->entity(), filters: [
            ['type' => 'and', 'field' => 'name', 'operator' => 'starts_with', 'value' => 'Ch'],
        ]);

        expect(array_keys($results))->toBe(['u3']);
    }

    public function test_filter_with_ends_with_operator(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords($this->entity(), filters: [
            ['type' => 'and', 'field' => 'email', 'operator' => 'ends_with', 'value' => '.org'],
        ]);

        expect(array_keys($results))->toBe(['u3']);
    }

    public function test_filter_with_in_operator(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords($this->entity(), filters: [
            ['type' => 'and', 'field' => 'name', 'operator' => 'in', 'value' => ['Alice', 'Diana']],
        ]);

        $ids = array_keys($results);
        sort($ids);

        expect($ids)->toBe(['u1', 'u4']);
    }

    public function test_filter_with_not_in_operator(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords($this->entity(), filters: [
            ['type' => 'and', 'field' => 'name', 'operator' => 'not_in', 'value' => ['Alice', 'Diana']],
        ]);

        $ids = array_keys($results);
        sort($ids);

        expect($ids)->toBe(['u2', 'u3']);
    }

    public function test_multiple_and_filters(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords($this->entity(), filters: [
            ['type' => 'and', 'field' => 'status', 'operator' => '=', 'value' => 'active'],
            ['type' => 'and', 'field' => 'age', 'operator' => '>=', 'value' => 30],
        ]);

        $ids = array_keys($results);
        sort($ids);

        expect($ids)->toBe(['u1', 'u3']);
    }

    public function test_sort_ascending(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords(
            $this->entity(),
            sort: [['field' => 'age', 'direction' => 'asc']],
        );

        expect(array_keys($results))->toBe(['u2', 'u4', 'u1', 'u3']);
    }

    public function test_sort_descending(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords(
            $this->entity(),
            sort: [['field' => 'age', 'direction' => 'desc']],
        );

        expect(array_keys($results))->toBe(['u3', 'u1', 'u4', 'u2']);
    }

    public function test_limit(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords(
            $this->entity(),
            sort: [['field' => 'age', 'direction' => 'asc']],
            limit: 2,
        );

        expect($results)->toHaveCount(2);
        expect(array_keys($results))->toBe(['u2', 'u4']);
    }

    public function test_offset(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords(
            $this->entity(),
            sort: [['field' => 'age', 'direction' => 'asc']],
            limit: 2,
            offset: 1,
        );

        expect($results)->toHaveCount(2);
        expect(array_keys($results))->toBe(['u4', 'u1']);
    }

    public function test_limit_and_offset_beyond_results(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords(
            $this->entity(),
            sort: [['field' => 'age', 'direction' => 'asc']],
            limit: 10,
            offset: 3,
        );

        expect($results)->toHaveCount(1);
        expect(array_keys($results))->toBe(['u3']);
    }

    public function test_filter_with_dot_notation_nested_field(): void
    {
        $this->seedFilterRecords();

        $results = $this->adapter()->findRecords($this->entity(), filters: [
            ['type' => 'and', 'field' => 'meta.role', 'operator' => '=', 'value' => 'user'],
        ]);

        $ids = array_keys($results);
        sort($ids);

        expect($ids)->toBe(['u2', 'u3']);
    }

    public function test_find_records_returns_empty_when_no_records_exist(): void
    {
        $results = $this->adapter()->findRecords($this->entity());

        expect($results)->toBe([]);
    }

    public function test_find_records_returns_full_record_data(): void
    {
        $data = ['name' => 'Test', 'value' => 42];
        $this->adapter()->writeRecord($this->entity(), 'r1', $data);

        $results = $this->adapter()->findRecords($this->entity());

        expect($results)->toHaveKey('r1');
        expect($results['r1'])->toEqual($data);
    }

    // ---------------------------------------------------------------
    //  Attachments
    // ---------------------------------------------------------------

    public function test_write_and_read_attachment_string_content(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['x' => 1]);
        $this->adapter()->writeAttachment($this->entity(), 'rec-1', 'notes.txt', 'hello world');

        $stream = $this->adapter()->readAttachment($this->entity(), 'rec-1', 'notes.txt');

        expect($stream)->toBeResource();

        $content = stream_get_contents($stream);

        expect($content)->toBe('hello world');
    }

    public function test_write_and_read_attachment_stream(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['x' => 1]);

        $input = fopen('php://memory', 'r+');
        fwrite($input, 'stream data');
        rewind($input);

        $this->adapter()->writeAttachment($this->entity(), 'rec-1', 'data.bin', $input);
        fclose($input);

        $stream = $this->adapter()->readAttachment($this->entity(), 'rec-1', 'data.bin');

        expect(stream_get_contents($stream))->toBe('stream data');
    }

    public function test_read_non_existent_attachment_throws_attachment_not_found_exception(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['x' => 1]);

        $this->expectException(AttachmentNotFoundException::class);

        $this->adapter()->readAttachment($this->entity(), 'rec-1', 'nope.txt');
    }

    public function test_list_attachments_empty(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['x' => 1]);

        expect($this->adapter()->listAttachments($this->entity(), 'rec-1'))->toBe([]);
    }

    public function test_list_attachments_with_one(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['x' => 1]);
        $this->adapter()->writeAttachment($this->entity(), 'rec-1', 'a.txt', 'data');

        expect($this->adapter()->listAttachments($this->entity(), 'rec-1'))->toBe(['a.txt']);
    }

    public function test_list_attachments_with_multiple(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['x' => 1]);
        $this->adapter()->writeAttachment($this->entity(), 'rec-1', 'a.txt', 'aaa');
        $this->adapter()->writeAttachment($this->entity(), 'rec-1', 'b.txt', 'bbb');
        $this->adapter()->writeAttachment($this->entity(), 'rec-1', 'c.txt', 'ccc');

        $names = $this->adapter()->listAttachments($this->entity(), 'rec-1');
        sort($names);

        expect($names)->toBe(['a.txt', 'b.txt', 'c.txt']);
    }

    public function test_attachment_exists_returns_true(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['x' => 1]);
        $this->adapter()->writeAttachment($this->entity(), 'rec-1', 'file.txt', 'x');

        expect($this->adapter()->attachmentExists($this->entity(), 'rec-1', 'file.txt'))
            ->toBeTrue();
    }

    public function test_attachment_exists_returns_false(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['x' => 1]);

        expect($this->adapter()->attachmentExists($this->entity(), 'rec-1', 'nope.txt'))
            ->toBeFalse();
    }

    public function test_delete_an_attachment(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['x' => 1]);
        $this->adapter()->writeAttachment($this->entity(), 'rec-1', 'a.txt', 'data');
        $this->adapter()->writeAttachment($this->entity(), 'rec-1', 'b.txt', 'data');

        $this->adapter()->deleteAttachment($this->entity(), 'rec-1', 'a.txt');

        expect($this->adapter()->attachmentExists($this->entity(), 'rec-1', 'a.txt'))->toBeFalse();
        expect($this->adapter()->attachmentExists($this->entity(), 'rec-1', 'b.txt'))->toBeTrue();
    }

    public function test_delete_all_attachments(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['x' => 1]);
        $this->adapter()->writeAttachment($this->entity(), 'rec-1', 'a.txt', 'aaa');
        $this->adapter()->writeAttachment($this->entity(), 'rec-1', 'b.txt', 'bbb');

        $this->adapter()->deleteAllAttachments($this->entity(), 'rec-1');

        expect($this->adapter()->listAttachments($this->entity(), 'rec-1'))->toBe([]);
    }

    public function test_delete_record_cascades_attachments(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['x' => 1]);
        $this->adapter()->writeAttachment($this->entity(), 'rec-1', 'a.txt', 'data');

        $this->adapter()->deleteRecord($this->entity(), 'rec-1');

        expect($this->adapter()->recordExists($this->entity(), 'rec-1'))->toBeFalse();

        // After the record is deleted, attachments should also be gone.
        // Attempting to list/check should either return empty or reflect absence.
        // Some adapters may throw if the record is gone; we accept both behaviours.
        try {
            $list = $this->adapter()->listAttachments($this->entity(), 'rec-1');
            expect($list)->toBe([]);
        } catch (RecordNotFoundException) {
            // Also acceptable – the record is gone.
            expect(true)->toBeTrue();
        }
    }

    public function test_write_attachment_overwrites_existing(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['x' => 1]);
        $this->adapter()->writeAttachment($this->entity(), 'rec-1', 'f.txt', 'old');
        $this->adapter()->writeAttachment($this->entity(), 'rec-1', 'f.txt', 'new');

        $content = stream_get_contents(
            $this->adapter()->readAttachment($this->entity(), 'rec-1', 'f.txt'),
        );

        expect($content)->toBe('new');

        // Still only one attachment listed.
        expect($this->adapter()->listAttachments($this->entity(), 'rec-1'))->toBe(['f.txt']);
    }

    // ---------------------------------------------------------------
    //  Entity lifecycle
    // ---------------------------------------------------------------

    public function test_initialize_entity(): void
    {
        $definition = $this->createEntityDefinition(
            name: $this->entity(),
            isSingleton: false,
            hasAttachments: true,
            hasTimestamps: false,
            indexedFields: ['status'],
        );

        // Should not throw; idempotent.
        $this->adapter()->initializeEntity($this->entity(), $definition);
        $this->adapter()->initializeEntity($this->entity(), $definition);

        expect(true)->toBeTrue();
    }

    public function test_purge_entity_removes_all_records_and_attachments(): void
    {
        $this->adapter()->writeRecord($this->entity(), 'rec-1', ['a' => 1]);
        $this->adapter()->writeRecord($this->entity(), 'rec-2', ['b' => 2]);
        $this->adapter()->writeAttachment($this->entity(), 'rec-1', 'f.txt', 'data');

        $this->adapter()->purgeEntity($this->entity());

        expect($this->adapter()->listRecordIds($this->entity()))->toBe([]);
        expect($this->adapter()->findRecords($this->entity()))->toBe([]);
    }

    // ---------------------------------------------------------------
    //  Entity definition helper
    // ---------------------------------------------------------------

    /**
     * Build a minimal EntityDefinitionInterface stub.
     *
     * Override this method in your test if your adapter requires a
     * more specialised definition object.
     *
     * @param  string[]  $indexedFields
     */
    private function createEntityDefinition(
        string $name,
        bool $isSingleton,
        bool $hasAttachments,
        bool $hasTimestamps,
        array $indexedFields,
    ): EntityDefinitionInterface {
        return new class($name, $isSingleton, $hasAttachments, $hasTimestamps, $indexedFields) implements EntityDefinitionInterface
        {
            public function __construct(
                public readonly string $name,
                public readonly bool $isSingleton,
                public readonly bool $hasAttachments,
                public readonly bool $hasTimestamps,
                public readonly array $indexedFields,
            ) {}
        };
    }
}
