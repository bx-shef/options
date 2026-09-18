<?php

namespace Shef\Options\Integration\IBlock;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

abstract class AEntity
{
	const MAX_SELECT_LIMIT = 100;
	const LOG_NAME = 'Shef-Options-Integration-IBlock-AEntity';
	protected static $skeepLog = true;

	/** @var \Shef\Options\Integration\IBlock\AElement $obj */
	protected static $obj = null;
	protected $fields = [];

	protected static $instances = [];

	// region Tools ////
	protected static function log(array $value = [], $isNotSkeep = false)
	{
		// skeep log ////
		if(!$isNotSkeep && static::$skeepLog)
		{
			return true;
		}

		_log($value, static::LOG_NAME);
		return true;
	}
	// endregion ////

	// region Construct ////
	public static function getInstance(): self
	{
		if(!isset(static::$instances[get_called_class()]))
		{
			static::$instances[get_called_class()] = new static();
		}

		return static::$instances[get_called_class()];
	}

	protected function includeModule()
	{
		try
		{
			return Loader::includeModule('iblock');

		}catch(LoaderException $exception)
		{
			return false;
		}
	}

	protected function __construct()
	{
		$this->includeModule();
		$this->initFields();
	}

	abstract public static function getIblockId();

	public static function getIblockName(): string
	{
		return 'NotSet';
	}

	public static function getIblockUrl(): string
	{
		return '/services/lists/'.static::getIblockId().'/view/0/';
	}

	public static function getIblockSort(): int
	{
		return 1000;
	}

	public static function isCanAddFromOrder(): bool
	{
		return false;
	}

	public static function getConfig()
	{
		$iblockId = static::getIblockId();
		return [
			'IBLOCK_ID' => $iblockId,
			'IBLOCK_SORT' => static::getIblockSort(),
			'IBLOCK_NAME' => static::getIblockName(),
			'IBLOCK_URL' => static::getIblockUrl(),
			'IBLOCK_SQL_ELEMENT_TABLE_ALIAS' => '`sh_z_'.$iblockId.'`',
			'IBLOCK_SQL_PROPS_TABLE' => '`b_iblock_element_prop_s'.$iblockId.'`',
			'IBLOCK_SQL_PROPS_TABLE_ALIAS' => '`sh_z_property_'.$iblockId.'`',
			'IBLOCK_SQL_TABLE' => static::getIblockUrl()
		];
	}
	// endregion ////

	// region Fields ////
	protected function getFieldsList()
	{
		return [];
	}

	protected function initFields()
	{
		/* @var \Shef\Options\Integration\IBlock\Fields\AField $fieldObj */
		$config = static::getConfig();
		$this->fields = [];
		foreach($this->getFieldsList() as $field)
		{
			$fieldObj = $field::getInstance($config);
			$this->fields[$fieldObj->getCode()] = &$fieldObj;
			unset($fieldObj);
		}
	}

	public function isHasField($code)
	{
		return array_key_exists($code, $this->fields);
	}

	/** @return \Shef\Options\Integration\IBlock\Fields\AField|null */
	public function getField($code)
	{
		if(isset($this->fields[$code]))
		{
			return $this->fields[$code];
		}
		else
		{
			return null;
		}
	}
	// endregion ////

	// region Select / Add / Update / Delete ////
	abstract protected static function getObject(): \Shef\Options\Integration\IBlock\AElement;

	public static function getList(array $params = []): array
	{
		/*/
		$conf = [
			static::getConfig()['IBLOCK_ID']
			,$params['filter']
			,$params['order'] ?? ['ID' => 'DESC']
			,false
			,$params['select'] ?? []
			,$params['limit'] ?? static::MAX_SELECT_LIMIT
		];
		_pr([$conf]);
		//*/
		return static::getObject()->getListInner(
			static::getConfig()['IBLOCK_ID']
			,$params['filter']
			,$params['order'] ?? ['ID' => 'DESC']
			,false
			,$params['select'] ?? []
			,$params['limit'] ?? static::MAX_SELECT_LIMIT
		);
	}

	public static function updateProps(int $elementId, array $props = []): \Bitrix\Main\Result
	{
		return static::getObject()->updatePropsEx(
			static::getConfig()['IBLOCK_ID']
			, $elementId
			, $props
		);
	}

	public static function delete(int $elementId): \Bitrix\Main\Result
	{
		return static::getObject()->delete($elementId);
	}

	public static function add(array $params = []): \Bitrix\Main\Result
	{
		$result = new \Bitrix\Main\Result();
		try
		{
			$params['IBLOCK_ID'] = static::getConfig()['IBLOCK_ID'];
			$response = static::getObject()->add($params);

			$result->setData(['ID' => (int)$response]);

		}catch(\Exception $e)
		{
			return $result->addError(new \Bitrix\Main\Error($e->getMessage()));
		}
		return $result;
	}

	public static function update(int $elementId, array $params = []): \Bitrix\Main\Result
	{
		$result = new \Bitrix\Main\Result();
		try
		{
			$elementId = (int)$elementId;
			$params['IBLOCK_ID'] = static::getConfig()['IBLOCK_ID'];
			unset($params['ID']);

			$response = static::getObject()->update($elementId, $params);
			$result->setData(['ID' => (int)$response]);

		}catch(\Exception $e)
		{
			return $result->addError(new \Bitrix\Main\Error($e->getMessage()));
		}
		return $result;
	}

	// endregion ////
}