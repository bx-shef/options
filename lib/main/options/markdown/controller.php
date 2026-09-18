<?php declare(strict_types=1);

namespace Shef\Options\Main\Options\Markdown;

use Bitrix\Main\IO\FileNotFoundException;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Application;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\IO;
use Shef\Options\Components\Actions;

/**
 * Ajax контроллер для вывода информации Markdown
 * @see Option
 */
class Controller
	extends \Bitrix\Main\Engine\Controller
{
	protected bool $isUseSmartyPants = true;
	
	protected function init(): void
	{
		parent::init();
	}
	
	public function configureActions(): array
	{
		return [
			'getContent' => Actions\Normal::get(),
		];
	}
	
	/**
	 * Отдаёт разметку документа из каталога модуля.
	 *
	 * Оба параметра приходят из браузера, поэтому каждый проверяется: права
	 * на модуль — здесь, путь — в DocumentPath.
	 */
	public function getContentAction(
		string $url,
		string $moduleId,
	): null|array
	{
		if(!$this->isAllowed($moduleId))
		{
			$this->addError(new Error('Access Denied'));
			return null;
		}
		
		$filePath = DocumentPath::resolve($this->getModulesRoot(), $moduleId, $url);
		if(null === $filePath)
		{
			$this->addError(new Error('File Not Exist'));
			return null;
		}
		
		$response = $this->getPrepareContent(new IO\File($filePath));
		if(!$response->isSuccess())
		{
			$this->addErrors($response->getErrors());
			return null;
		}
		
		$content = $response->getData()['CONTENT'];
		
		return [
			'content' => $content
		];
	}
	
	/**
	 * Права проверяются в самом действии, а не только на показ страницы:
	 * адрес действия виден в коде страницы и вызывается напрямую.
	 *
	 * Проверка та же, которой страница настроек модуля пускает к себе, —
	 * право на сам модуль. Администратору ядро отдаёт «W» и без настроенных
	 * прав, но полагаться только на это нельзя: $APPLICATION существует не в
	 * любом окружении.
	 */
	private function isAllowed(string $moduleId): bool
	{
		global $APPLICATION;
		
		if(CurrentUser::get()->isAdmin())
		{
			return true;
		}
		
		return is_object($APPLICATION) && $APPLICATION->GetGroupRight($moduleId) >= 'R';
	}
	
	private function getModulesRoot(): string
	{
		return sprintf(
			'%s/bitrix/modules',
			Application::getDocumentRoot()
		);
	}
	
	/**
	 * @throws FileNotFoundException
	 */
	private function getPrepareContent(IO\File $file): Result
	{
		$result = new Result();
		$content = MarkdownExtra::defaultTransform($file->getContents());
		
		if($this->isUseSmartyPants)
		{
			$content = \Michelf\SmartyPants::defaultTransform($content);
		}
		
		return $result->setData([
			'CONTENT' => &$content
		]);
	}
}
