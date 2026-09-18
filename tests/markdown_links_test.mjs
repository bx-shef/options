/**
 * Разбор ссылок во вкладке «Документация».
 *
 * Документация живёт в репозитории, а README из поставки ссылается на неё по
 * адресам GitHub. Эти адреса кончаются на «.md» — как и внутренние ссылки, —
 * поэтому решать по одному расширению нельзя: внешняя ссылка ушла бы в ajax,
 * который ищет файл в каталоге модуля, и вернулась ошибкой.
 *
 * Здесь же ловится расхождение script.js и script.min.js: минифицированная
 * копия едет в поставку и используется, и правка одной без другой проехала бы
 * молча.
 *
 * Тест зовёт настоящий onClickHref и смотрит, какая ветка сработала. Повторять
 * условие у себя нельзя: так проверялась бы копия логики, а не код модуля.
 */

import fs from 'node:fs';
import vm from 'node:vm';

const cases = [
	['docs/2_installer.md', 'ajax'],
	['CHANGELOG.md', 'ajax'],
	['https://github.com/bx-shef/options/blob/main/docs/2_installer.md', 'внешняя'],
	['http://example.com/a.md', 'внешняя'],
	['//cdn.example.com/x.md', 'внешняя'],
	['mailto:offer@bx-shef.by', 'внешняя'],
	['https://bx-shef.by/', 'внешняя'],
	['style.css', 'внешняя'],
];

// Заглушка ядра: от BX нужны только namespace и проверка типа.
function makeBX()
{
	const BX = function(){ return null; };
	BX.namespace = function(path)
	{
		let cur = BX;
		for(const part of path.split('.').slice(1))
		{
			cur[part] = cur[part] || {};
			cur = cur[part];
		}
		return cur;
	};
	BX.type = { isPlainObject: () => true };
	return BX;
}

function load(file)
{
	const src = fs.readFileSync(`install/js/shef-options/options-markdown/${file}`, 'utf8');
	const ctx = { BX: makeBX(), document: { createElement: () => ({}) }, console };
	vm.createContext(ctx);
	vm.runInContext(src, ctx);
	return ctx.BX.ShOptions.Markdown.prototype;
}

/**
 * Прогоняет настоящий обработчик клика и возвращает выбранную им ветку.
 */
function route(proto, url)
{
	let outcome = 'ничего';

	const self = Object.create(proto);
	self.params = { actionUrl: 'shef:options.markdown.getContent', rootPath: '', moduleId: 'shef.options' };
	self.linkForOpen = { href: null, click(){ outcome = 'внешняя'; } };
	self.fade = () => {};
	self.unFade = () => {};
	self.callMethod = () =>
	{
		outcome = 'ajax';
		return { then: () => ({ catch: () => {} }) };
	};

	proto.onClickHref.call(self, {
		target: { getAttribute: () => url },
		preventDefault(){},
		stopPropagation(){},
		stopImmediatePropagation(){},
	});

	return outcome;
}

let bad = 0;

for(const file of ['script.js', 'script.min.js'])
{
	const proto = load(file);
	console.log(`--- ${file} ---`);

	for(const [url, expected] of cases)
	{
		const got = route(proto, url);
		const ok = got === expected;
		if(!ok)
		{
			bad++;
		}
		console.log(`  ${ok ? 'OK  ' : 'FAIL'} ${url} -> ${got}${ok ? '' : `, ожидалось ${expected}`}`);
	}
}

// Внешние ссылки должны открываться в новой вкладке. Значение target пишется
// строкой, и опечатка в нём тихо даёт именованное окно вместо новой вкладки.
for(const file of ['script.js', 'script.min.js'])
{
	const src = fs.readFileSync(`install/js/shef-options/options-markdown/${file}`, 'utf8');
	const match = src.match(/linkForOpen\.target\s*=\s*(["'])(.*?)\1/);
	const value = match ? match[2] : null;
	const ok = value === '_blank';
	if(!ok)
	{
		bad++;
	}
	console.log(`  ${ok ? 'OK  ' : 'FAIL'} ${file}: linkForOpen.target = ${JSON.stringify(value)}`);
}

console.log(bad === 0 ? '\nОбе копии ведут себя одинаково и верно.' : `\nОШИБОК: ${bad}`);
process.exit(bad === 0 ? 0 : 1);
