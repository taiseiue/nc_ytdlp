<?php

declare(strict_types=1);

namespace OCA\NcYtdlp\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getUrl()
 * @method void setUrl(string $url)
 * @method string getFormat()
 * @method void setFormat(string $format)
 * @method string getDestination()
 * @method void setDestination(string $destination)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getTitle()
 * @method void setTitle(?string $title)
 * @method string|null getErrorMessage()
 * @method void setErrorMessage(?string $errorMessage)
 * @method string|null getCookie()
 * @method void setCookie(?string $cookie)
 * @method string|null getOutputTemplate()
 * @method void setOutputTemplate(?string $outputTemplate)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Download extends Entity implements \JsonSerializable {
	protected string $userId = '';
	protected string $url = '';
	protected string $format = '';
	protected string $destination = '';
	protected string $status = '';
	protected ?string $title = null;
	protected ?string $errorMessage = null;
	protected ?string $cookie = null;
	protected ?string $outputTemplate = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'userId' => $this->userId,
			'url' => $this->url,
			'format' => $this->format,
			'destination' => $this->destination,
			'status' => $this->status,
			'title' => $this->title,
			'errorMessage' => $this->errorMessage,
			'hasCookie' => $this->cookie !== null && $this->cookie !== '',
			'outputTemplate' => $this->outputTemplate,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
