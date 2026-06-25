<?php

declare(strict_types=1);

namespace OCA\NcYtdlp\Controller;

use OCA\NcYtdlp\AppInfo\Application;
use OCA\NcYtdlp\Db\Template;
use OCA\NcYtdlp\Db\TemplateMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class TemplateController extends Controller {
	private const MAX_NAME_LENGTH = 255;
	private const MAX_TEMPLATE_LENGTH = 500;

	public function __construct(
		IRequest $request,
		private readonly TemplateMapper $templateMapper,
		private readonly IUserSession $userSession,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'Not authenticated'], 401);
		}
		$templates = $this->templateMapper->findAllByUser($user->getUID());
		return new DataResponse(array_map(fn ($t) => $t->jsonSerialize(), $templates));
	}

	#[NoAdminRequired]
	public function create(string $name, string $template): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'Not authenticated'], 401);
		}

		$name = trim($name);
		$template = trim($template);

		if ($name === '' || $template === '') {
			return new DataResponse(['error' => 'Name and template are required.'], 400);
		}
		if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
			return new DataResponse(['error' => 'Name is too long.'], 400);
		}
		if (mb_strlen($template) > self::MAX_TEMPLATE_LENGTH) {
			return new DataResponse(['error' => 'Template is too long.'], 400);
		}
		// Mirror the download output template restriction: no path separators.
		if (str_contains($template, '/')) {
			return new DataResponse(['error' => 'Template must not contain path separators (/).'], 400);
		}

		$entity = new Template();
		$entity->setUserId($user->getUID());
		$entity->setName($name);
		$entity->setTemplate($template);
		$entity->setCreatedAt(time());
		$entity->setUpdatedAt(time());

		$entity = $this->templateMapper->insert($entity);

		return new DataResponse($entity->jsonSerialize(), 201);
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'Not authenticated'], 401);
		}
		try {
			$entity = $this->templateMapper->findById($id, $user->getUID());
			$this->templateMapper->delete($entity);
			return new DataResponse(['success' => true]);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'Not found'], 404);
		}
	}
}
