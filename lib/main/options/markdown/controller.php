<?php declare(strict_types=1);

namespace Shef\Options\Main\Options\Markdown;

use Bitrix\Main\IO\FileNotFoundException;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Application;
use Bitrix\Main\LoaderException;
use Bitrix\Main\IO;
use Shef\Options\Main;
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
	
	public function getContentAction(
		string $url,
		string $moduleId,
	): null|array
	{
		$filePath = sprintf(
			'%s/%s',
			$this->getModulePath($moduleId),
			$url
		);
		
		$response = $this->getFile($filePath);
		if(!$response->isSuccess())
		{
			$this->addErrors($response->getErrors());
			return null;
		}
		
		$response = $this->getPrepareContent($response->getData()['FILE']);
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
	
	private function getModulePath(string $moduleId): string
	{
		return sprintf(
			'%s/bitrix/modules/%s',
			Application::getDocumentRoot(),
			$moduleId
		);
	}
	
	private function getFile(string $filePath): Result
	{
		$result = new Result();
		
		$file = new IO\File($filePath);
		if(!$file->isExists())
		{
			return $result->addError(new Error('File Not Exist'));
		}
		elseif($file->getExtension() !== 'md')
		{
			return $result->addError(new Error('File Not Markdown'));
		}
		
		return $result->setData([
			'FILE' => $file
		]);
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