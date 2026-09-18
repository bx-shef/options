<?php declare(strict_types=1);

namespace Shef\Options\TraitList\Tools;

use Bitrix\Main;
use Bitrix\Main\Error;
use Bitrix\Main\ArgumentNullException;

/**
 * Трейт для работы с ошибками
 * implements \Bitrix\Main\Errorable
 *
 * @see \Bitrix\Main\Errorable
 */
trait ErrorCollection
{
    protected ?Main\ErrorCollection $errorCollection = null;

    /**
     * @return void
     */
    protected function initErrorCollection(): void
    {
        $this->errorCollection = new Main\ErrorCollection;
    }

    /**
     * @return Main\ErrorCollection
     * @throws ArgumentNullException
     */
    protected function getErrorCollection(): Main\ErrorCollection
    {
        if(!($this->errorCollection instanceof Main\ErrorCollection))
        {
            throw new \Bitrix\Main\ArgumentNullException('errorCollection');
        }

        return $this->errorCollection;
    }

    /**
     * Adds error to error collection.
     * @param Error $error Error.
     *
     * @return $this
     * @throws ArgumentNullException
     */
    protected function addError(Error $error): self
    {
        $this->getErrorCollection()->setError($error);
        return $this;
    }

    /**
     * Adds list of errors to error collection.
     * @param Error[] $errors Errors.
     *
     * @return $this
     * @throws ArgumentNullException
     */
    protected function addErrors(array $errors): self
    {
        $this->getErrorCollection()->add($errors);
        return $this;
    }

    /**
     * Getting array of errors.
     * @return Error[]
     * @throws ArgumentNullException
     */
    final public function getErrors(): array
    {
        return $this->getErrorCollection()->toArray();
    }

    /**
     * Getting once error with the necessary code.
     * @param string|int $code Code of error.
     * @return Error|null
     */
    final public function getErrorByCode($code): ?Error
    {
        return $this->errorCollection->getErrorByCode($code);
    }

    /**
     * Выводит на ошибки
     *
     * @param string $option
     * @return void
     * @throws ArgumentNullException
     */
    protected function printErrors(string $option = 'errortext'): void
    {
        foreach ($this->getErrors() as $error)
        {
            if(!function_exists('_showError'))
            {
                ShowError($error, $option);
            }
            else
            {
                _showError($error, $option);
            }
        }
    }

    /**
     * По ошибкам генерирует исключение \Exception
     *
     * @return void
     * @throws ArgumentNullException
     */
    protected function throwErrors(): void
    {
        $messages = [];
        foreach($this->getErrors() as $error)
        {
            $messages[] = $error->getMessage();
        }

        if(!empty($messages))
        {
            throw new \Exception(implode(', ', $messages));
        }

    }
}