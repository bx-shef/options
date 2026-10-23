<?php

declare(strict_types=1);

/**
 * Форма настроек в виде «Настроек BitrixGPT» (3.1.0, перенос из shef.toolsai, bx-shef/toolsai#96) — Ui\SettingsForm, без ядра.
 *
 * Что держит:
 *
 * * вкладки shef.options -> секции; поле на настройку с именем
 *   param_<ВКЛАДКА>_<код> — тем, что читает цикл сохранения страницы;
 * * типы: Checkbox -> переключатель (checked по Y), NumberInt -> число,
 *   TextArea, Enum -> выбор с текущим значением, RowInfo -> текст;
 * * подсказка «?» — из FAQ по полному коду;
 * * описание: ссылки и <br> остаются, остальной HTML экранируется,
 *   javascript: не проходит;
 * * supports(): форма умеет все поля вкладок — иначе страница оставляет прежние вкладки.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/bitrix.php';
require_once $root.'/tests/assert.php';
// Заглушки ядра, которых настоящим классам вкладок не хватает в tests/stub/bitrix.php
foreach ([
    'if(!class_exists("CTextParser", false)) { class CTextParser { public array $allow = []; public function convertText(string $text): string { return $text; } } }',
    'namespace Bitrix\Main\UI; if(!class_exists(Extension::class)) { class Extension { public static function load($e): void {} } }',
] as $code) {
    eval($code);
}
// Настоящие классы модуля: Shef\Options\X\Y -> lib/x/y.php
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'Shef\\Options\\')) {
        $file = $root.'/lib/'.strtolower(str_replace('\\', '/', substr($class, strlen('Shef\\Options\\')))).'.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

use Shef\Options\Main\Options;
use Shef\Options\Ui\SettingsForm;

Check::group('поля');

$tab = (new Options\Tab('DEF'))->setName('Движок')->setTitle('Свой движок')
    ->addOption((new Options\RowInfo('About'))->setDescription('См. <a href="/shef/toolsai/?section=quota&amp;x=1">расход</a><br>и <script>alert(1)</script>'))
    ->addOption((new Options\Checkbox('on'))->setTitle('Включено'))
    ->addOption((new Options\NumberInt('quota'))->setTitle('Квота')->setDescription('<a href="javascript:alert(1)">x</a>'))
    ->addOption((new Options\TextArea('crit'))->setTitle('Критерии'))
    ->addOption((new Options\Enum('prov'))->setTitle('Провайдер')->setList(['echo' => 'Заглушка', 'openai' => 'OpenAI']))
    ->addOption((new Options\Text('url'))->setTitle('Адрес'));

$values = ['DEF_on' => 'Y', 'DEF_quota' => '500', 'DEF_crit' => "a\nb", 'DEF_prov' => 'openai', 'DEF_url' => 'https://x.by'];
$form = SettingsForm::fromTabs([$tab], static fn (string $code): string => $values[$code] ?? '', static fn (string $code): string => $code === 'DEF_quota' ? 'Сколько можно потратить' : '');

Check::same('секция', [$form[0]['id'], $form[0]['title'], $form[0]['description']], ['DEF', 'Движок', 'Свой движок']);
Check::same('типы по порядку', array_column($form[0]['fields'], 'type'), ['info', 'checker', 'number', 'textarea', 'selector', 'text']);
Check::same('имена — как у shef.options', array_column($form[0]['fields'], 'name'), ['', 'param_DEF_on', 'param_DEF_quota', 'param_DEF_crit', 'param_DEF_prov', 'param_DEF_url']);
Check::same('переключатель включён по Y', $form[0]['fields'][1]['checked'], true);
Check::same('выключен по N', SettingsForm::fromTabs([$tab], static fn (string $c): string => 'N')[0]['fields'][1]['checked'], false);
Check::same('выбор — текущее значение отмечено', $form[0]['fields'][4]['items'], [
    ['value' => 'echo', 'name' => 'Заглушка', 'selected' => false],
    ['value' => 'openai', 'name' => 'OpenAI', 'selected' => true],
]);
Check::same('значение текста', $form[0]['fields'][5]['value'], 'https://x.by');
Check::same('подсказка из FAQ', $form[0]['fields'][2]['hint'], 'Сколько можно потратить');
$withPh = SettingsForm::withPlaceholders($form, ['DEF_url' => 'сейчас: https://a.by', 'DEF_quota' => 'сейчас: 5', 'DEF_on' => 'x', 'DEF_crit' => 'x']);
Check::same('#294: серый текст — текстовым и числовым полям, прочим нет', array_column($withPh[0]['fields'], 'placeholder', 'name'), ['param_DEF_quota' => 'сейчас: 5', 'param_DEF_url' => 'сейчас: https://a.by']);
Check::same('#294: JS отдаёт его полю ввода', str_contains(SettingsForm::script(), "input.placeholder = field.placeholder || ''"), true);
Check::same('#292: без значка — пусто', $form[0]['fields'][2]['hintIcon'], '');
$withIcon = SettingsForm::fromTabs([$tab], static fn (string $code): string => '', static fn (string $code): string => 'x', static fn (string $code): string => $code === 'DEF_quota' ? '<a class="shef-tai-q">?</a>' : '');
Check::same('#292: значок «?» — HTML ссылки по полному коду', [$withIcon[0]['fields'][2]['hintIcon'], $withIcon[0]['fields'][5]['hintIcon']], ['<a class="shef-tai-q">?</a>', '']);
Check::same('#292: JS ставит значок ссылкой, а не «?» текстом', str_contains(SettingsForm::script(), "htmlNode('shef-ui-row__q', field.hintIcon)"), true);

Check::group('описания');

$info = $form[0]['fields'][0]['html'];
Check::same('ссылка и <br> остаются', str_contains($info, '<a href="/shef/toolsai/?section=quota&amp;x=1" target="_top">расход</a><br>'), true);
Check::same('скрипт экранирован', str_contains($info, '<script>'), false);
Check::same('javascript: не ссылка', str_contains($form[0]['fields'][2]['description'], '<a '), false);

Check::same('«//host» не ссылка', str_contains(SettingsForm::safeHtml('<a href="//evil.by">x</a>'), '<a '), false);
Check::same('«/\\host» не ссылка', str_contains(SettingsForm::safeHtml('<a href="/\\evil.by">x</a>'), '<a '), false);
Check::same('data: не ссылка', str_contains(SettingsForm::safeHtml('<a href="data:text/html,1">x</a>'), '<a '), false);
Check::same('ссылка от парсера shef.options (лишние атрибуты) — ссылка, атрибуты прочь', SettingsForm::safeHtml('Эндпоинт движка: <a href="https://b24.x.by/endpoint.php" target="_blank" rel="nofollow" onclick="evil()">https://b24.x.by/endpoint.php</a>.'), 'Эндпоинт движка: <a href="https://b24.x.by/endpoint.php" target="_top">https://b24.x.by/endpoint.php</a>.');
Check::same('атрибуты без href — текст', SettingsForm::safeHtml('<a onclick="x()">y</a>'), '&lt;a onclick=&quot;x()&quot;&gt;y</a>');
Check::same('javascript: в href при лишних атрибутах — не ссылка', str_contains(SettingsForm::safeHtml('<a target="_blank" href="javascript:alert(1)">x</a>'), '<a '), false);
Check::same('https — ссылка', str_contains(SettingsForm::safeHtml('<a href="https://x.by/a?b=1&c=2">x</a>'), '<a href="https://x.by/a?b=1&amp;c=2" target="_top">'), true);

Check::group('сохранение');

$post = ['shef_ui_rendered' => 'Y', 'param_DEF_on' => 'N', 'param_DEF_url' => ' https://y.by ', 'param_DEF_prov' => 'echo'];
Check::same('только пришедшие поля', SettingsForm::collect([$tab], $post), ['DEF_on' => 'N', 'DEF_prov' => 'echo', 'DEF_url' => 'https://y.by']);
Check::same('форма не отрисована — ничего', SettingsForm::collect([$tab], ['param_DEF_url' => '']), []);
Check::same('переключатель Y', SettingsForm::collect([$tab], ['shef_ui_rendered' => 'Y', 'param_DEF_on' => 'Y']), ['DEF_on' => 'Y']);
Check::same('переключатель мусор — нет', SettingsForm::collect([$tab], ['shef_ui_rendered' => 'Y', 'param_DEF_on' => 'x']), ['DEF_on' => 'N']);

Check::group('JS передаёт имена полей ядру');

$js = SettingsForm::script();
Check::same('переключатель: inputName = имя поля', (bool)preg_match('/new BX\.UI\.Switcher\(\{\s*inputName: field\.name/', $js), true);
Check::same('переключатель: скрытое поле Y/N сразу, не пустое', str_contains($js, "switcher.inputNode.value = field.checked === true ? 'Y' : 'N'"), true);
Check::same('выбор, текст, многострочный: name = имя поля', str_contains($js, 'input.name = field.name;'), true);

Check::group('ключи API — полем-паролем, значение в страницу не уходит (1.64.5, #283)');

$secretTab = new Options\Tab('API');
$secretTab->setName('API')->setTitle('API')
    ->addOption((new Options\Text('apikey'))->setTitle('Общий ключ API'))
    ->addOption((new Options\Text('baseurl'))->setTitle('Адрес'));
$secretForm = SettingsForm::withSecrets(SettingsForm::fromTabs([$secretTab], static fn (string $code): string => ['API_apikey' => 'sk-live-1234567890abcd', 'API_baseurl' => 'https://api.x.by'][$code] ?? ''), ['API_apikey']);
Check::same('ключ: тип secret, значения нет, маска — последние 4 знака', [$secretForm[0]['fields'][0]['type'], $secretForm[0]['fields'][0]['value'], str_starts_with($secretForm[0]['fields'][0]['placeholder'], '••••abcd')], ['secret', '', true]);
Check::same('ключа нет нигде в данных страницы', str_contains((string)json_encode($secretForm), 'sk-live'), false);
Check::same('прочие поля не тронуты', [$secretForm[0]['fields'][1]['type'], $secretForm[0]['fields'][1]['value']], ['text', 'https://api.x.by']);
Check::same('ключ не задан — без маски', SettingsForm::withSecrets(SettingsForm::fromTabs([$secretTab], static fn (string $code): string => ''), ['API_apikey'])[0]['fields'][0]['placeholder'], '');
Check::same('короткий ключ — маска без знаков', SettingsForm::mask('abc'), '••••');
Check::same('сохранение: пусто — ключ не трогаем, «-» — удалить, прочее — как пришло', [
    SettingsForm::keepSecrets(['API_apikey' => '', 'API_baseurl' => ''], ['API_apikey']),
    SettingsForm::keepSecrets(['API_apikey' => '-'], ['API_apikey']),
    SettingsForm::keepSecrets(['API_apikey' => 'sk-new'], ['API_apikey']),
], [['API_baseurl' => ''], ['API_apikey' => ''], ['API_apikey' => 'sk-new']]);
Check::same('JS: поле-пароль и кнопка «Показать»', [str_contains(SettingsForm::script(), "input.type = 'password'"), str_contains(SettingsForm::script(), "'Показать'")], [true, true]);
Check::same('все ключи направлений — в списке секретов', ['x'], ['x']);

Check::group('карточки (1.64.0)');

$meta = SettingsForm::withMeta($form, ['DEF' => ['--o-mail', '/shef/toolsai/kb/guide/emails']]);
Check::same('иконка и справка по коду вкладки', [$meta[0]['icon'], $meta[0]['help']], ['--o-mail', '/shef/toolsai/kb/guide/emails']);
Check::same('нет записи — шестерёнка, без справки', [SettingsForm::withMeta($form, [])[0]['icon'], SettingsForm::withMeta($form, [])[0]['help']], ['--o-settings', null]);
Check::same('кривой класс иконки — шестерёнка', SettingsForm::withMeta($form, ['DEF' => ['x" onclick="y', null]])[0]['icon'], '--o-settings');
Check::same('стиль карточек на месте', str_contains(SettingsForm::style(), '.shef-ui-card__head'), true);
Check::same('штатных ui.section и ui.form-elements больше не грузим', array_values(array_intersect(SettingsForm::extensions(), ['ui.section', 'ui.form-elements.view'])), []);
Check::same('JS формы на месте', str_contains(SettingsForm::script(), 'window.ShefUiSettingsForm'), true);

Check::group('название с HTML-ссылкой «?» (модули дописывают FAQ в название)');
$faqTab = (new Options\Tab('P'))->setName('P')->addOption((new Options\Checkbox('on'))->setTitle('Показывать <a class="q" href="/shef/x/faq/#faq-P" onclick="evil()" title="a">?</a>'));
$faqField = SettingsForm::fromTabs([$faqTab], static fn (string $c): string => 'Y')[0]['fields'][0];
Check::same('название — без тегов и без «?»', $faqField['label'], 'Показывать');
Check::same('ссылка — значком, только href, без onclick', [str_contains($faqField['hintIcon'], 'href="/shef/x/faq/#faq-P"'), str_contains($faqField['hintIcon'], 'onclick'), str_contains($faqField['hintIcon'], 'title=')], [true, false, false]);
Check::same('без ссылки в названии — значка нет', SettingsForm::fromTabs([$tab], static fn (string $c): string => '')[0]['fields'][1]['hintIcon'], '');

Check::group('supports(): все поля вкладок форма умеет (3.1.0)');
Check::same('вкладка из поддержанных полей — карточки', SettingsForm::supports([$tab]), true);
$people = new Options\Department('PEOPLE');
Check::same('есть поле, которого форма не умеет (Department), — прежние вкладки', SettingsForm::supports([(new Options\Tab('X'))->setName('X')->addOption($people)]), false);
Check::same('пусто — прежние вкладки', SettingsForm::supports([]), false);
$page = (string)file_get_contents($root.'/options.php');
Check::same('options.php: карточки только при supports(); сохранение карточек — collect() (только пришедшие поля)', [str_contains($page, 'if (class_exists(SettingsForm::class) && SettingsForm::supports($cardTabs))'), str_contains($page, 'SettingsForm::collect($cardTabs, $request->getPostList()->toArray())'), str_contains($page, '$tabControl->Begin();')], [true, true, true]);

Check::finish();
