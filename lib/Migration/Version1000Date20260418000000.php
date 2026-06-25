<?php

declare(strict_types=1);

namespace OCA\NcYtdlp\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20260418000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('nc_ytdlp_downloads')) {
			$table = $schema->createTable('nc_ytdlp_downloads');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('url', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('format', Types::STRING, [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('destination', Types::STRING, [
				'notnull' => true,
				'length' => 4000,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 20,
				'default' => 'pending',
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => false,
				'length' => 500,
			]);
			$table->addColumn('error_message', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'nc_ytdlp_user_id_idx');
			$table->addIndex(['status'], 'nc_ytdlp_status_idx');
		}

		return $schema;
	}
}
