<?php

declare(strict_types=1);

namespace OCA\NcYtdlp\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1002Date20260419000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('nc_ytdlp_downloads');

		if (!$table->hasColumn('output_template')) {
			$table->addColumn('output_template', Types::STRING, [
				'notnull' => false,
				'length' => 500,
				'default' => null,
			]);
		}

		return $schema;
	}
}
