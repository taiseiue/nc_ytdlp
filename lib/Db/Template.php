<?php

declare(strict_types=1);

namespace OCA\NcYtdlp\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getTemplate()
 * @method void setTemplate(string $template)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Template extends Entity implements \JsonSerializable {
	protected string $userId = '';
	protected string $name = '';
	protected string $template = '';
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'userId' => $this->userId,
			'name' => $this->name,
			'template' => $this->template,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
