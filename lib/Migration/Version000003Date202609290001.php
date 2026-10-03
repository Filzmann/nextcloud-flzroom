<?php

declare(strict_types=1);

namespace OCA\AdRoom\Migration;

use Closure;
use OCA\AdRoom\BackgroundJob\InterventionNotificationJob;
use OCP\BackgroundJob\IJobList;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Additive fresh-install schema for append-only intervention evidence and its notification outbox. */
final class Version000003Date202609290001 extends SimpleMigrationStep {
    public function __construct(private IJobList $jobs) {
    }

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('adr_booking_audit')) {
            $table = $schema->createTable('adr_booking_audit');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('action', Types::STRING, ['length' => 16, 'notnull' => true]);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('booking_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('occurred_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('old_room_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('new_room_id', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('old_room_name', Types::STRING, ['length' => 255, 'notnull' => true]);
            $table->addColumn('new_room_name', Types::STRING, ['length' => 255, 'notnull' => false]);
            $table->addColumn('old_starts_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('old_ends_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('new_starts_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->addColumn('new_ends_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->addColumn('reason', Types::STRING, ['length' => 500, 'notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['occurred_at', 'id'], 'adr_baudit_retention');
            $table->addIndex(['actor_uid', 'occurred_at'], 'adr_baudit_actor');
            $table->addIndex(['booking_id', 'occurred_at'], 'adr_baudit_booking');
        }

        if (!$schema->hasTable('adr_notify_queue')) {
            $table = $schema->createTable('adr_notify_queue');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('recipient_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('action', Types::STRING, ['length' => 16, 'notnull' => true]);
            $table->addColumn('booking_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('old_room_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('new_room_id', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('old_room_name', Types::STRING, ['length' => 255, 'notnull' => true]);
            $table->addColumn('new_room_name', Types::STRING, ['length' => 255, 'notnull' => false]);
            $table->addColumn('old_starts_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('old_ends_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('new_starts_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->addColumn('new_ends_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->addColumn('reason', Types::STRING, ['length' => 500, 'notnull' => true]);
            $table->addColumn('attempt_count', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $table->addColumn('next_attempt_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('state', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'pending']);
            $table->addColumn('failed_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->addColumn('error_code', Types::STRING, ['length' => 64, 'notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['state', 'next_attempt_at'], 'adr_notify_due');
            $table->addIndex(['state', 'failed_at'], 'adr_notify_retention');
            $table->addIndex(['recipient_uid', 'created_at'], 'adr_notify_recipient');
        }
        return $schema;
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        if (!$this->jobs->has(InterventionNotificationJob::class, null)) {
            $this->jobs->add(InterventionNotificationJob::class);
        }
    }
}
