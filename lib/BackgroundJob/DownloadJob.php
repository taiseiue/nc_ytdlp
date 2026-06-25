<?php

declare(strict_types=1);

namespace OCA\NcYtdlp\BackgroundJob;

use OCA\NcYtdlp\Db\DownloadMapper;
use OCA\NcYtdlp\Service\YtdlpService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

class DownloadJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private readonly DownloadMapper $downloadMapper,
		private readonly YtdlpService $ytdlpService,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	protected function run(mixed $argument): void {
		$downloadId = isset($argument['download_id']) ? (int)$argument['download_id'] : 0;

		if ($downloadId <= 0) {
			$this->logger->error('DownloadJob: Invalid download_id in argument', $argument);
			return;
		}

		try {
			$download = $this->downloadMapper->findByIdForJob($downloadId);
		} catch (DoesNotExistException) {
			$this->logger->error('DownloadJob: Download record not found', ['id' => $downloadId]);
			return;
		}

		// Mark as running
		$download->setStatus('running');
		$download->setUpdatedAt(time());
		$this->downloadMapper->update($download);

		try {
			$title = $this->ytdlpService->download(
				$download->getUserId(),
				$download->getUrl(),
				$download->getFormat(),
				$download->getDestination(),
				$download->getCookie(),
				$download->getOutputTemplate(),
			);

			$download->setStatus('completed');
			$download->setTitle($title);
		} catch (\Throwable $e) {
			$this->logger->error('DownloadJob: Download failed', [
				'id' => $downloadId,
				'url' => $download->getUrl(),
				'error' => $e->getMessage(),
			]);
			$download->setStatus('failed');
			$download->setErrorMessage(mb_substr($e->getMessage(), 0, 1000));
		}

		$download->setUpdatedAt(time());
		$this->downloadMapper->update($download);
	}
}
