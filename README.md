# shef.options
Для хранения настроек, паттернов, трейтов, абстракций и интерфейсов.

Модуль содержит в себе только программную часть которую можно использовать в других модулях.

Модуль устанавливается обычным способом.

> В настройках модуля нужно заполнить поле Служебный пользователь.
> Это пользователь с правами администратора, которого не уволят.
> Как минимум пользователь с ID = 1
> 
> Другие модули могут использовать ID указанного пользователя в своей работе

# Документация
* [change log](CHANGELOG.md)
* [Installer](docs/2_installer.md)
* [Опции настроек модуля](docs/3_options.md)
* [Работа с пользователями](docs/4_security.md)
* [Утилиты](docs/5_utils.md)
* [Работа с компонентами](docs/6_components.md)
* [Паттерны](docs/7_pattern.md)
* [Тестирование](docs/8_tests.md)
* [Набор трейтов](docs/9_traitlist.md)

# Если модуль деактивировали и работа сломалась
Делаем заглушку на вызов.
Пример:
```php
<?php
if(!\Bitrix\Main\ModuleManager::isModuleInstalled('shef.options'))
{
	class Events
	{
		public static function __callStatic(string $name, array $arguments): mixed
		{
			return null;
		}
	}
	
	return;
}
?>
```