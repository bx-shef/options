<?php declare(strict_types=1);

namespace Shef\Options\Main\Options;

use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web;
use Shef\Options\Options\SmartStd;

/**
 * Вывод сотрудников и отделов
 * нужен моуль intranet
 */
class Department
	extends AOption
{
	protected bool $isSingleMode = false;
	protected bool $isOnlyDepartments = false;
	protected bool $isOnlyUsers = false;
	protected bool $isNotUseAllUsers = false;
	
	public function __construct(
		string $code
	)
	{
		parent::__construct($code);
		
		Extension::load([
			'ui.entity-selector',
		]);
	}
	
	// region Get/Set ////
	public function setSingleMode(bool $value): static
	{
		$this->isSingleMode = $value;
		return $this;
	}

	public function isSingleMode(): bool
	{
		return $this->isSingleMode;
	}
	
	public function setOnlyDepartments(bool $value): static
	{
		$this->isOnlyDepartments = $value;
		return $this;
	}

	public function isOnlyDepartments(): bool
	{
		return $this->isOnlyDepartments;
	}
	
	public function setOnlyUsers(bool $value): static
	{
		$this->isOnlyUsers = $value;
		return $this;
	}

	public function isOnlyUsers(): bool
	{
		return $this->isOnlyUsers;
	}
	
	public function setNotUseAllUsers(bool $value): static
	{
		$this->isNotUseAllUsers = $value;
		return $this;
	}

	public function isNotUseAllUsers(): bool
	{
		return $this->isNotUseAllUsers;
	}

	public function isMultiple(): bool
	{
		return false;
	}
	// endregion ////
	
	// region Render /////
	/**
	 * @link https://dev.1c-bitrix.ru/api_d7/bitrix/ui/entity_selector/index.php
	 */
	protected function renderValue(string $moduleId): string
	{
		if(!\Bitrix\Main\Loader::includeModule('intranet'))
		{
			ob_start();
			ShowError('Module intranet not loaded');
			$error = ob_get_contents();
			ob_end_clean();
			return $error;
		}
		
		$value = $this->getInputValue($moduleId);
		$value = htmlspecialcharsback($value);
		try
		{
			$value = Web\Json::decode($value);
			if(!is_array($value))
			{
				$value = [];
			}
			
			$value = array_values($value);
		}
		catch(\Throwable $throwable)
		{
			$value = [];
		}
		
		// region CSS ////
		ob_start();?>
		<style>
			.content-department input.ui-tag-selector-item.ui-tag-selector-text-box
			{
				border: 0;
				box-shadow: none;
			}
		</style>
		<?php
		$style = ob_get_contents();
		ob_end_clean();
		// endregion ////
		
		// region JS ////
		$confJs = [
			'containerId' => 'department_'.$this->getCode(),
			'inputName' => $this->getInputName(),
			'inputId' => $this->getInputId(),
		];
		
		$entityUser = new SmartStd;
		$entityUser->id = 'user';
		
		$entityDepartment = new SmartStd;
		$entityDepartment->id = 'department';
		$entityDepartment->options = new SmartStd;
		
		
		if($this->isOnlyDepartments())
		{
			$entityDepartment->options->selectMode = 'departmentsOnly';
		}
		elseif(!$this->isOnlyUsers())
		{
			$entityDepartment->options->selectMode = 'usersAndDepartments';
		}
		
		$entityAllUsers = new SmartStd;
		$entityAllUsers->id = 'meta-user';
		$entityAllUsers->options = [];
		$entityAllUsers->options['all-users'] = true;
		
		$entities = [];
		
		if($this->isOnlyDepartments())
		{
			$entities[] = $entityDepartment->toArray();
		}
		else
		{
			$entities[] = $entityUser->toArray();
			$entities[] = $entityDepartment->toArray();
			if(!$this->isNotUseAllUsers())
			{
				$entities[] = $entityAllUsers->toArray();
			}
		}
		ob_start();?>
		<script>
			BX.ready(() => {
				let data = [];
				const nodeInput = document.getElementById('<?=$confJs['inputId']?>');
				const tagSelector = new BX.UI.EntitySelector.TagSelector({
					showAvatars: false,
					multiple: <?=( $this->isSingleMode() ? 'false' : 'true')?>,
					dialogOptions: {
						context: 'SHEF_OPTIONS',
						showAvatars: true,
						compactView: false,
						entities: <?=Web\Json::encode($entities)?>
					},
					events: {
						onBeforeTagAdd: function(event) {
							const { tag } = event.getData();
							if(!!tag)
							{
								tag.link = '';
							}
						}.bind(this),
						onAfterTagAdd: function(event)
						{
							const { tag } = event.getData();
							
							data = data.filter(function(e) { return e.id !== tag.getId(); }.bind(this));
							
							data.push({
								id: tag.getId(),
								title: tag.getTitle(),
								entityId: tag.getEntityId(),
								entityType: tag.getEntityType(),
								link: '' ,
								avatar: tag.getAvatar(),
							});
							
							nodeInput.value = JSON.stringify(data);
						}.bind(this),
						onBeforeTagRemove: function(event)
						{
							const { tag } = event.getData();
							
							data = data.filter(function(e) { return e.id !== tag.getId(); }.bind(this));
							
							nodeInput.value = JSON.stringify(data);
						}.bind(this),
					}
				});
				tagSelector.renderTo(document.getElementById('<?=$confJs['containerId']?>'));
				
				try
				{
					data = JSON.parse(nodeInput.value);
				}
				catch(error)
				{
					data = [];
				}
				
				data.forEach(function(currentValue){
					tagSelector.addTag(currentValue);
				}.bind(this));
			});
		</script>
		<?php
		$js = ob_get_contents();
		ob_end_clean();
		// endregion ////
		
		return sprintf(
			'%s<div class="content-department"><input name="%s" id="%s" type="hidden" value=\'%s\'><div class="content-department-container" id="%s"></div></div>%s',
			$style,
			(string) $confJs['inputName'],
			(string) $confJs['inputId'],
			Web\Json::encode($value),
			(string) $confJs['containerId'],
			$js
		);
	}
	// endregion ////
}