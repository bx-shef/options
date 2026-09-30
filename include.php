<?php

require_once __DIR__.'/autoload.php';

// def-functions.php объявляет _log() и _pr(). Их зовёт код, который модуль
// предлагает использовать: трейт TraitList\Log, Integration\AEvents,
// Integration\IBlock\AEntity. Без этой строки функций не существовало — файл
// ехал в поставке и не подключался ниоткуда, и первый же вызов Log::log()
// падал с «Call to undefined function _log()».
//
// Двойного объявления не будет: обе функции закрыты function_exists. Объявил
// проект свои раньше — из php_interface/init.php, который платформа читает до
// подключения модулей, — останутся проектные. Сам в php_interface проекта
// модуль не лезет: см. CLAUDE.md, решения владельца.
require_once __DIR__.'/def-functions.php';

require_once __DIR__.'/register-js.php';
