<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Zweck: Legt die app-eigenen Raeume und Buchungen ohne WordPress-Abhaengigkeiten an. */
final class Version000001Date202607130001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('flz_room_rooms')) {
            $rooms = $schema->createTable('flz_room_rooms');
            $rooms->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $rooms->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
            $rooms->addColumn('description', Types::STRING, ['length' => 500, 'notnull' => true, 'default' => '']);
            $rooms->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $rooms->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $rooms->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $rooms->setPrimaryKey(['id']);
            $rooms->addUniqueIndex(['name'], 'flz_room_room_name');
            $rooms->addIndex(['sort_order', 'name'], 'flz_room_room_order');
        }

        if (!$schema->hasTable('flz_room_bookings')) {
            $bookings = $schema->createTable('flz_room_bookings');
            $bookings->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $bookings->addColumn('room_id', Types::BIGINT, ['notnull' => true]);
            $bookings->addColumn('user_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $bookings->addColumn('purpose', Types::STRING, ['length' => 255, 'notnull' => true]);
            $bookings->addColumn('title', Types::STRING, ['length' => 255, 'notnull' => true]);
            $bookings->addColumn('starts_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $bookings->addColumn('ends_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $bookings->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $bookings->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $bookings->setPrimaryKey(['id']);
            $bookings->addIndex(['room_id', 'starts_at', 'ends_at'], 'flz_room_room_range');
            $bookings->addIndex(['user_uid', 'starts_at'], 'flz_room_user_start');
        }

        return $schema;
    }
}
