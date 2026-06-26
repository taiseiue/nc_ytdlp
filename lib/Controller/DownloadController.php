<?php

declare(strict_types=1);

namespace OCA\NcYtdlp\Controller;

use OCA\NcYtdlp\AppInfo\Application;
use OCA\NcYtdlp\BackgroundJob\DownloadJob;
use OCA\NcYtdlp\Db\Download;
use OCA\NcYtdlp\Db\DownloadMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class DownloadController extends Controller {
	public function __construct(
		IRequest $request,
		private readonly DownloadMapper $downloadMapper,
		private readonly IJobList $jobList,
		private readonly IUserSession $userSession,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'Not authenticated'], 401);
		}
		$downloads = $this->downloadMapper->findAllByUser($user->getUID());
		return new DataResponse(array_map(fn ($d) => $d->jsonSerialize(), $downloads));
	}

	#[NoAdminRequired]
	public function create(string $url, string $format, string $destination, string $cookie = '', string $outputTemplate = ''): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'Not authenticated'], 401);
		}
		$userId = $user->getUID();

		if (!in_array($format, ['mp4', 'mp3'], true)) {
			return new DataResponse(['error' => 'Invalid format. Use mp4 or mp3.'], 400);
		}

		if (filter_var($url, FILTER_VALIDATE_URL) === false) {
			return new DataResponse(['error' => 'Invalid URL.'], 400);
		}

		$destination = trim($destination) ?: '/';

		// Validate output template: must not contain path separators to prevent directory traversal in temp dir
		$outputTemplate = trim($outputTemplate);
		if ($outputTemplate !== '' && str_contains($outputTemplate, '/')) {
			return new DataResponse(['error' => 'Output template must not contain path separators (/).'], 400);
		}

		$download = new Download();
		$download->setUserId($userId);
		$download->setUrl($url);
		$download->setFormat($format);
		$download->setDestination($destination);
		$download->setCookie($cookie !== '' ? $cookie : null);
		$download->setOutputTemplate($outputTemplate !== '' ? $outputTemplate : null);
		$download->setStatus('pending');
		$download->setCreatedAt(time());
		$download->setUpdatedAt(time());

		$download = $this->downloadMapper->insert($download);

		$this->jobList->add(DownloadJob::class, ['download_id' => $download->getId()]);

		return new DataResponse($download->jsonSerialize(), 201);
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'Not authenticated'], 401);
		}
		try {
			$userId = $user->getUID();
			$download = $this->downloadMapper->findById($id, $userId);
			$this->downloadMapper->delete($download);
			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'Not found'], 404);
		}
	}

	#[NoAdminRequired]
	/**
	 * Delete completed and failed download history entries for the authenticated user.
	 */
	public function clearHistory(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'Not authenticated'], 401);
		}

		try {
			$deleted = $this->downloadMapper->deleteHistoryByUser($user->getUID());
			return new DataResponse([
				'success' => true,
				'deleted' => $deleted,
			]);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to clear download history', [
				'userId' => $user->getUID(),
				'error' => $e->getMessage(),
				'exception' => $e,
			]);
			return new DataResponse(['error' => 'Failed to clear history'], 500);
		}
	}
}
