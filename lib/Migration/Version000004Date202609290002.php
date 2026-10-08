<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Additive app-local retention holds; active holds never expire automatically. */
final class Version000004Date202609290002 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output,Closure $schemaClosure,array $options):?ISchemaWrapper{$schema=$schemaClosure();if($schema->hasTable('flz_room_retention_holds'))return null;$table=$schema->createTable('flz_room_retention_holds');
        $table->addColumn('id',Types::BIGINT,['autoincrement'=>true,'notnull'=>true]);$table->addColumn('policy_id',Types::STRING,['length'=>64,'notnull'=>true]);$table->addColumn('record_ref',Types::STRING,['length'=>255,'notnull'=>true]);$table->addColumn('reason_code',Types::STRING,['length'=>64,'notnull'=>true]);$table->addColumn('evidence_ref',Types::STRING,['length'=>255,'notnull'=>true]);$table->addColumn('placed_by',Types::STRING,['length'=>64,'notnull'=>true]);$table->addColumn('placed_at',Types::DATETIME_IMMUTABLE,['notnull'=>true]);$table->addColumn('review_due_at',Types::DATETIME_IMMUTABLE,['notnull'=>true]);$table->addColumn('released_by',Types::STRING,['length'=>64,'notnull'=>false]);$table->addColumn('released_at',Types::DATETIME_IMMUTABLE,['notnull'=>false]);$table->setPrimaryKey(['id']);$table->addIndex(['policy_id','record_ref','released_at'],'flz_room_ret_hold_active');$table->addIndex(['review_due_at','released_at'],'flz_room_ret_hold_review');return$schema;}
}
