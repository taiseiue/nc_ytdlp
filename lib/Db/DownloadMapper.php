<?php

declare(strict_types=1);

namespace OCA\NcYtdlp\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Download>
 */
class DownloadMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'nc_ytdlp_downloads', Download::class);
	}

	/**
	 * @return Download[]
	 */
	public function findAllByUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('created_at', 'DESC')
			->setMaxResults(100);
		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 */
	public function findById(int $id, string $userId): Download {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 */
	public function findByIdForJob(int $id): Download {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Delete completed and failed downloads for the given user.
	 *
	 * @return int Number of deleted rows
	 */
	public function deleteHistoryByUser(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('status', $qb->createNamedParameter('completed')),
					$qb->expr()->eq('status', $qb->createNamedParameter('failed')),
				)
			);

		return $qb->executeStatement();
	}
}
