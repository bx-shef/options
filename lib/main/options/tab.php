<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

use Shef\Options\TraitList;

/**
 * Работа с закладкой в настройках
 */
class Tab
{
	use TraitList\Tools\XmlId;
	
	protected string $code = '';
	protected string $name = '';
	protected string $title = '';
	protected string $icon = 'sale_settings';
	protected string $description = '';

	/** @var array|AOption[] */
	protected array $options = [];

	public function __construct(string $code)
	{
		$this->code = $code;
	}

	// region Get/Set ////
	public function getCode(): string
	{
		return $this->code;
	}

	public function setName(string $value): static
	{
		$this->name = $value;
		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setTitle(string $value): static
	{
		$this->title = $value;
		return $this;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function setIcon(string $value): static
	{
		$this->icon = $value;
		return $this;
	}

	public function getIcon(): string
	{
		return $this->icon;
	}

	public function getBitrixAdminArray(): array
	{
		return [
			'DIV' => 'edit_'.$this->getCode(),
			'TAB' => $this->getName(),
			'ICON' => $this->getIcon(),
			'TITLE' => $this->getTitle()
		];
	}

	public function setDescription(string $value): static
	{
		$this->description = $value;
		return $this;
	}

	public function getDescription(): string
	{
		return $this->description;
	}
	
	public function getDescriptionFormatted(): string
	{
		$obParser = new \CTextParser;
		$descr = $obParser->convertText($this->getDescription());
		unset($obParser);
		
		return $descr;
	}

	public function isDescription(): bool
	{
		return mb_strlen($this->getDescription()) > 0;
	}

	public function addOption(AOption $value): static
	{
		$this->options[] = $value;
		$value->setTab($this);
		return $this;
	}

	/**
	 * @return array|AOption[]
	 */
	public function getOptionList(): array
	{
		return $this->options;
	}
	// endregion ////
	
	// region Render ////
	public function render(string $moduleId): string
	{
		if(!$this->isDescription())
		{
			return '';
		}
		
		return sprintf(
			join('', [
				'<tr><div class="ui-alert %s"><span class="ui-alert-message">',
				'%s',
				'</span></div></tr>',
			]),
			TypeUIAlert::Note->value,
			$this->getDescriptionFormatted()
		);
	}
	
	public function renderOption(string $moduleId, AOption $option): string
	{
		return sprintf(
			'<tr>%s</tr>',
			$option->render($moduleId)
		);
	}
	// endregion ////

	// region Tools ////
	/**
	 * @param array|Tab[] $list
	 * @return array
	 */
	public static function prepareListForBitrixAdminArray(array $list): array
	{
		$result = [];

		/** @var Tab $tab */
		foreach($list as $tab)
		{
			if(!($tab instanceof Tab))
			{
				continue;
			}

			$result[] = $tab->getBitrixAdminArray();
		}
		return $result;
	}
	// endregion ////
}