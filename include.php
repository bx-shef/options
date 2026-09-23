<?php

require_once __DIR__.'/autoload.php';

// def-functions.php объявляет _log(), _log1() и _pr(). Их зовёт код, который
// модуль предлагает использовать: трейт TraitList\Log, Integration\AEvents,
// Integration\IBlock\AEntity. Без этой строки функций не существовало — файл
// ехал в поставке и не подключался ниоткуда, и первый же вызов Log::log()
// падал с «Call to undefined function _log()».
//
// Двойного объявления не будет: каждая функция закрыта function_exists, а сам
// файл сначала подтягивает php_interface/def-functions.php проекта, если тот
// есть, — версия проекта остаётся главной.
require_once __DIR__.'/def-functions.php';

require_once __DIR__.'/register-js.php';
?>