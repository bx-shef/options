<?php declare(strict_types=1);

namespace Shef\Options\Main;

/**
 * @see \Bitrix\Crm\Service\Context
 */
class Context
{
	public const SCOPE_MANUAL = 'manual';
	
	/**
	 * @memo agents, background jobs
	 */
	public const SCOPE_TASK = 'task';
	public const SCOPE_AUTOMATION = 'automation';
	public const SCOPE_REST = 'rest';

	protected $eventId;
	protected $userId;
	protected $scope;
	protected array $itemOptions = [];

	public function __construct(array $params = [])
	{
		foreach($params as $name => $value)
		{
			if(property_exists(static::class, $name))
			{
				$this->$name = $value;
			}
		}
	}

	public function setUserId(int $userId): static
	{
		$this->userId = $userId;

		return $this;
	}

	public function getUserId(): int
	{
		if($this->userId !== null)
		{
			return (int) $this->userId;
		}

		return $this->getCurrentUserId();
	}

	public function setScope(string $scope): static
	{
		$this->scope = $scope;

		return $this;
	}

	public function getScope(): string
	{
		if($this->scope)
		{
			return (string) $this->scope;
		}

		return static::SCOPE_MANUAL;
	}

	protected function getCurrentUserId(): int
	{
		global $USER;
		if(is_object($USER) && $USER instanceof \CUser)
		{
			return (int) Security::getCurrentUserId();
		}

		return 0;
	}

	public function getEventId(): ?string
	{
		return $this->eventId;
	}

	public function setEventId(?string $eventId): static
	{
		$this->eventId = $eventId;
		return $this;
	}

	/**
	 * @param string $optionName
	 * @return mixed|null
	 */
	public function getItemOption(string $optionName): mixed
	{
		$options = $this->getItemOptions();
		return ($options[$optionName] ?? null);
	}

	public function getItemOptions(): array
	{
		return $this->itemOptions;
	}

	public function setItemOption(string $optionName, $value): static
	{
		$this->itemOptions[$optionName] = $value;
		return $this;
	}

	public function setItemOptions(array $itemOptions): static
	{
		$this->itemOptions = $itemOptions;
		return $this;
	}
}
