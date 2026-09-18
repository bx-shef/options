#!/usr/bin/env bash
#
# Единственная точка входа сборки и проверок. Её же зовёт CI.
#
#   ./build.sh            проверки + архив shef.options.zip
#   ./build.sh --check    только проверки
#   ./build.sh --version  напечатать версию модуля
#
# Файлы модуля лежат в корне репозитория: Composer разворачивает в целевой
# каталог корень пакета целиком и подкаталоги выбирать не умеет. Поэтому здесь
# два списка — что уезжает на портал и что остаётся в репозитории.
#
# ВАЖНО: файл, не попавший ни в один список, роняет сборку. Это единственная
# страховка раскладки «модуль в корне»: без неё новый файл однажды уедет на
# портал молча.
#
# Тот же список продублирован в .gitattributes через export-ignore — он решает,
# что попадёт в Composer-пакет (git archive его соблюдает). Списки обязаны
# совпадать, иначе на портал уедет разное в зависимости от способа установки.
# Сверяется автоматически, см. check_gitattributes.

set -euo pipefail

MODULE_ID='shef.options'

# Уезжает на портал и в Composer-пакет.
SHIP=(
	'.settings.php'
	'CHANGELOG.md'
	'CLAUDE.md'
	'LICENSE'
	'README.md'
	'autoload.php'
	'composer.json'
	'def-functions.php'
	'default_option.php'
	'include.php'
	'options.php'
	'options_conf.php'
	'optionsconfig.php'
	'project-context.php'
	'register-js.php'
	'docs/'
	'install/'
	'lang/'
	'lib/'
	'vendor/'
)

# Остаётся в репозитории.
KEEP=(
	'.gitattributes'
	'.github/'
	'.gitignore'
	'CONTRIBUTING.md'
	'build.sh'
	'tests/'
)

# ---------------------------------------------------------------------------

RED=''; GREEN=''; YELLOW=''; RESET=''
if [ -t 1 ]; then
	RED=$'\033[31m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; RESET=$'\033[0m'
fi

FAILED=0

fail()
{
	echo "${RED}[FAIL]${RESET} $*" >&2
	FAILED=1
}

ok()
{
	echo "${GREEN}[ OK ]${RESET} $*"
}

note()
{
	echo "${YELLOW}[ .. ]${RESET} $*"
}

# Путь начинается с записи-каталога (запись оканчивается на «/»)?
in_dir_entry()
{
	local file="$1"; shift
	local entry
	for entry in "$@"
	do
		if [ "${entry%/}" != "$entry" ] && [ "${file#"$entry"}" != "$file" ]
		then
			return 0
		fi
	done

	return 1
}

# Путь совпадает с записью-файлом точно?
is_exact_entry()
{
	local file="$1"; shift
	local entry
	for entry in "$@"
	do
		if [ "$entry" = "$file" ]
		then
			return 0
		fi
	done

	return 1
}

# Куда относится файл: ship | keep | none | both
#
# Точное совпадение сильнее совпадения по каталогу: так файл из KEEP можно
# положить внутрь каталога из SHIP, не разнося каталоги по разным деревьям.
classify()
{
	local file="$1"
	local exactShip=0 exactKeep=0

	is_exact_entry "$file" "${SHIP[@]}" && exactShip=1
	is_exact_entry "$file" "${KEEP[@]}" && exactKeep=1

	if [ $exactShip -eq 1 ] && [ $exactKeep -eq 1 ]
	then
		echo both; return 0
	fi
	if [ $exactShip -eq 1 ]
	then
		echo ship; return 0
	fi
	if [ $exactKeep -eq 1 ]
	then
		echo keep; return 0
	fi

	if in_dir_entry "$file" "${SHIP[@]}"
	then
		echo ship; return 0
	fi
	if in_dir_entry "$file" "${KEEP[@]}"
	then
		echo keep; return 0
	fi

	echo none
}

# Файлы под контролем git — то же, что увидит git archive.
tracked_files()
{
	git ls-files
}

ship_files()
{
	local f
	while IFS= read -r f
	do
		if [ "$(classify "$f")" = 'ship' ]
		then
			printf '%s\n' "$f"
		fi
	done < <(tracked_files)
}

get_version()
{
	php -r '
		$arModuleVersion = [];
		require __DIR__."/install/version.php";
		echo (string)($arModuleVersion["VERSION"] ?? "");
	'
}

get_version_date()
{
	php -r '
		$arModuleVersion = [];
		require __DIR__."/install/version.php";
		echo (string)($arModuleVersion["VERSION_DATE"] ?? "");
	'
}

# Текстовый файл? (пустой считаем текстовым)
is_text()
{
	[ -s "$1" ] || return 0
	LC_ALL=C grep -qI . "$1" 2>/dev/null
}

# region Проверки ////

# Каждый файл репозитория должен попасть ровно в один список.
check_lists()
{
	local f verdict bad=0

	while IFS= read -r f
	do
		verdict="$(classify "$f")"
		case "$verdict" in
			ship|keep) ;;
			both)
				fail "файл числится и в SHIP, и в KEEP: $f"
				bad=1
				;;
			*)
				fail "файл не числится ни в SHIP, ни в KEEP: $f"
				bad=1
				;;
		esac
	done < <(tracked_files)

	if [ $bad -eq 0 ]
	then
		ok "списки SHIP/KEEP покрывают все $(tracked_files | wc -l | tr -d ' ') файлов"
	else
		echo "      Допишите файл в SHIP (уезжает на портал) или в KEEP (остаётся здесь)," >&2
		echo "      и продублируйте KEEP в .gitattributes через export-ignore." >&2
	fi
}

# Списки в .gitattributes обязаны совпадать со списком KEEP.
check_gitattributes()
{
	local entry pattern bad=0

	if [ ! -f .gitattributes ]
	then
		fail '.gitattributes не найден'
		return
	fi

	for entry in "${KEEP[@]}"
	do
		pattern="${entry%/}"
		if ! grep -qE "^/?${pattern//./\\.}(/)?[[:space:]]+export-ignore([[:space:]]|$)" .gitattributes
		then
			fail "в .gitattributes нет export-ignore для KEEP-записи: $entry"
			bad=1
		fi
	done

	# Обратная сторона: export-ignore на файле из SHIP увёл бы его из
	# Composer-пакета, оставив в zip. Ровно то расхождение, ради которого
	# списки и сверяются.
	while IFS= read -r pattern
	do
		[ -n "$pattern" ] || continue
		pattern="${pattern#/}"
		if ! is_exact_entry "$pattern" "${KEEP[@]}" && ! is_exact_entry "$pattern/" "${KEEP[@]}"
		then
			fail "в .gitattributes есть export-ignore, которого нет в KEEP: $pattern"
			bad=1
		fi
	done < <(grep -E '[[:space:]]export-ignore([[:space:]]|$)' .gitattributes | awk '{print $1}')

	if [ $bad -eq 0 ]
	then
		ok ".gitattributes совпадает со списком KEEP (${#KEEP[@]} записей)"
	fi
}

# Модуль поставляется в UTF-8: установщик кодировку не правит, и файл в CP1251
# доехал бы до портала мусором. Дешевле не пустить его в репозиторий.
check_encoding()
{
	local f bad=0

	while IFS= read -r f
	do
		is_text "$f" || continue

		if ! iconv -f UTF-8 -t UTF-8 "$f" >/dev/null 2>&1
		then
			fail "файл не в UTF-8: $f"
			bad=1
			continue
		fi

		if [ "$(LC_ALL=C head -c 3 "$f" | od -An -tx1 | tr -d ' \n')" = 'efbbbf' ]
		then
			fail "BOM в начале файла: $f"
			bad=1
		fi
	done < <(tracked_files)

	if [ $bad -eq 0 ]
	then
		ok 'все текстовые файлы в UTF-8, без BOM'
	fi
}

# php -l гоняем настройками по умолчанию: на боевом short_open_tag выключен,
# и файл с «<?» там отдался бы в браузер исходником.
check_php()
{
	local f count=0 bad=0

	while IFS= read -r f
	do
		case "$f" in *.php) ;; *) continue ;; esac
		count=$((count + 1))
		if ! php -d short_open_tag=Off -l "$f" >/dev/null 2>&1
		then
			fail "php -l: $f"
			php -d short_open_tag=Off -l "$f" 2>&1 | sed 's/^/      /' >&2 || true
			bad=1
		fi
	done < <(tracked_files)

	if [ $bad -eq 0 ]
	then
		ok "php -l прошёл по $count файлам"
	fi
}

# Короткий тег «<?» при short_open_tag=Off (значение по умолчанию) открывающим
# тегом не считается: файл целиком становится инлайновым HTML, классы в нём не
# определяются, а исходник уезжает в браузер. php -l это пропускает — файл
# синтаксически «правильный», просто в нём нет PHP.
#
# Спрашиваем сам PHP, а не grep: «<?» внутри строки или комментария лежит в
# своём токене, а опасный — остаётся куском T_INLINE_HTML.
check_short_tags()
{
	local f lines bad=0

	while IFS= read -r f
	do
		case "$f" in *.php) ;; *) continue ;; esac

		lines="$(php -d short_open_tag=Off -r '
			$src = file_get_contents($argv[1]);
			foreach(token_get_all($src) as $token)
			{
				if(is_array($token) && $token[0] === T_INLINE_HTML && str_contains($token[1], "<?"))
				{
					echo $token[2], " ";
				}
			}
		' "$f" 2>/dev/null)"

		if [ -n "$lines" ]
		then
			fail "короткий тег «<?» (нужен «<?php»): $f, строки: ${lines% }"
			bad=1
		fi
	done < <(tracked_files)

	if [ $bad -eq 0 ]
	then
		ok 'коротких тегов «<?» нет'
	fi
}

check_js()
{
	local f count=0 bad=0

	if ! command -v node >/dev/null 2>&1
	then
		note 'node не найден — проверка JS пропущена'
		return
	fi

	while IFS= read -r f
	do
		case "$f" in *.js) ;; *) continue ;; esac
		count=$((count + 1))
		if ! node --check "$f" >/dev/null 2>&1
		then
			fail "node --check: $f"
			node --check "$f" 2>&1 | sed 's/^/      /' >&2 || true
			bad=1
		fi
	done < <(tracked_files)

	if [ $bad -eq 0 ]
	then
		ok "node --check прошёл по $count файлам"
	fi
}

# registerNamespace отображает класс в путь строчными: Foo\Bar ищется как
# lib/foo/bar.php. На macOS заглавная буква сходит с рук, на боевом Linux — нет.
check_lowercase()
{
	local f bad=0

	while IFS= read -r f
	do
		case "$f" in lib/*) ;; *) continue ;; esac
		if [ "$f" != "$(printf '%s' "$f" | tr '[:upper:]' '[:lower:]')" ]
		then
			fail "заглавные буквы в пути lib/: $f"
			bad=1
		fi
	done < <(tracked_files)

	if [ $bad -eq 0 ]
	then
		ok 'в lib/ все имена строчными'
	fi
}

check_version()
{
	local version date

	if [ ! -f install/version.php ]
	then
		fail 'install/version.php не найден'
		return
	fi

	version="$(get_version)"
	date="$(get_version_date)"

	if [ -z "$version" ]
	then
		fail 'в install/version.php пустой VERSION'
		return
	fi

	if ! printf '%s' "$version" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+$'
	then
		fail "VERSION не вида X.Y.Z: $version"
		return
	fi

	if [ -z "$date" ]
	then
		fail 'в install/version.php пустой VERSION_DATE'
		return
	fi

	ok "версия $version от $date"
}

run_tests()
{
	local t count=0 bad=0

	for t in tests/*_test.php
	do
		[ -e "$t" ] || continue
		count=$((count + 1))
		if ! php "$t"
		then
			fail "тест не прошёл: $t"
			bad=1
		fi
	done

	if [ $count -eq 0 ]
	then
		note 'тестов в tests/*_test.php пока нет'
		return
	fi

	if [ $bad -eq 0 ]
	then
		ok "тесты прошли: $count"
	fi
}

run_checks()
{
	echo "Проверки $MODULE_ID"
	echo
	check_lists
	check_gitattributes
	check_encoding
	check_php
	check_short_tags
	check_js
	check_lowercase
	check_version
	run_tests
}

# endregion ////

# region Сборка ////

# Архив содержит каталог модуля целиком: первым уровнем внутри zip — <ID>/,
# иначе при распаковке файлы рассыплются по modules/.
build_archive()
{
	local out="$PWD/$MODULE_ID.zip"
	local stage f topLevel

	stage="$(mktemp -d)"

	while IFS= read -r f
	do
		mkdir -p "$stage/$MODULE_ID/$(dirname "$f")"
		cp -p "$f" "$stage/$MODULE_ID/$f"
	done < <(ship_files)

	rm -f "$out"
	( cd "$stage" && zip -q -r -X "$out" "$MODULE_ID" )
	rm -rf "$stage"

	# Первый уровень внутри архива — ровно один каталог, и это <ID>/.
	topLevel="$(unzip -Z1 "$out" | cut -d/ -f1 | sort -u)"
	if [ "$topLevel" != "$MODULE_ID" ]
	then
		fail "первый уровень архива не «$MODULE_ID/», а: $(printf '%s' "$topLevel" | tr '\n' ' ')"
		return
	fi

	# Состав архива обязан совпасть со списком SHIP.
	if ! diff <(unzip -Z1 "$out" | grep -v '/$' | sed "s#^$MODULE_ID/##" | sort) \
	          <(ship_files | sort) >/dev/null
	then
		fail 'состав архива разошёлся со списком SHIP'
		diff <(unzip -Z1 "$out" | grep -v '/$' | sed "s#^$MODULE_ID/##" | sort) \
		     <(ship_files | sort) | sed 's/^/      /' >&2 || true
		return
	fi

	ok "архив $MODULE_ID.zip: $(unzip -Z1 "$out" | grep -cv '/$') файлов"
}

# То, что отдаст Composer (git archive), обязано совпасть с тем, что уедет
# в zip. Иначе на портал приедет разное в зависимости от способа установки.
check_composer_package()
{
	if [ -n "$(git status --porcelain)" ]
	then
		note 'рабочее дерево грязное — сверка с git archive пропущена'
		return
	fi

	if ! diff <(git archive --format=tar HEAD | tar -tf - | grep -v '/$' | sort) \
	          <(ship_files | sort) >/dev/null
	then
		fail 'состав Composer-пакета (git archive) разошёлся со списком SHIP'
		diff <(git archive --format=tar HEAD | tar -tf - | grep -v '/$' | sort) \
		     <(ship_files | sort) | sed 's/^/      /' >&2 || true
		return
	fi

	ok 'состав Composer-пакета совпадает со списком SHIP'
}

# endregion ////

main()
{
	cd "$(dirname "$0")"

	case "${1-}" in
		--version)
			get_version
			echo
			return 0
			;;
		--check)
			run_checks
			check_composer_package
			;;
		'')
			run_checks
			check_composer_package
			echo
			build_archive
			;;
		*)
			echo "Неизвестный аргумент: $1" >&2
			echo "Использование: $0 [--check|--version]" >&2
			return 2
			;;
	esac

	echo
	if [ $FAILED -ne 0 ]
	then
		echo "${RED}Проверки не прошли.${RESET}" >&2
		return 1
	fi

	echo "${GREEN}Готово.${RESET}"
}

main "$@"
