<?php

declare(strict_types=1);

namespace Shef\Options\Log;

/**
 * Маска секретов для журнала и архива поддержки (bx-shef/toolsai#612).
 *
 * Зовётся дважды: при записи строки в журнал и при сборке архива — по каждому файлу.
 * Вторая проверка ловит то, что попало в источники мимо журнала (лог ядра, старые файлы).
 *
 * Секрет заменяется на «••••» и последние 4 знака: по ним поддержка сверяет, какой ключ
 * стоит на портале, а сам ключ из файла не восстановить. Короче 12 знаков — только «••••».
 */
final class Mask
{
    public const DOTS = '••••';

    /** Имена полей и параметров, значения которых — секрет (без учёта регистра, «-» = «_»). */
    private const SECRET_NAMES = 'api_?key|x_api_key|apikey|access_?token|refresh_?token|id_?token|auth_?token|token|secret|client_?secret|password|passwd|pass|pwd|cookie|set_cookie|authorization|auth|sessid|phpsessid|webhook|private_?key|license_?key|key';

    public static function tail(string $value): string
    {
        $len = mb_strlen($value);

        return $len >= 12 ? self::DOTS.mb_substr($value, -4) : self::DOTS;
    }

    public static function isSecretName(string $name): bool
    {
        $name = strtolower(str_replace('-', '_', trim($name)));

        return (bool)preg_match('/^(?:'.self::SECRET_NAMES.')$/', $name)
            || (bool)preg_match('/(?:^|_)(?:token|secret|password|passwd|apikey|api_key|cookie|authorization|webhook)$/', $name);
    }

    public static function string(string $text): string
    {
        if ($text === '') {
            return $text;
        }
        $tail = static fn (array $m): string => self::tail($m[0]);
        $rules = [
            // Заголовки целиком: Authorization: …, Cookie: …
            '/\b(authorization|proxy-authorization|cookie|set-cookie)(\s*:\s*)([^\r\n|]+)/i' => static fn (array $m): string => $m[1].$m[2].self::DOTS,
            // Bearer/Basic в любом месте
            '/\b(Bearer|Basic)\s+([A-Za-z0-9._~+\/=-]{6,})/' => static fn (array $m): string => $m[1].' '.self::tail($m[2]),
            // Сертификат лицензии BXL1.…, JWT
            '/\bBXL1\.[A-Za-z0-9_\-]+(?:\.[A-Za-z0-9_\-]+)?/' => $tail,
            '/\beyJ[A-Za-z0-9_\-]{5,}\.[A-Za-z0-9_\-]{5,}\.[A-Za-z0-9_\-]{5,}/' => $tail,
            // Ключи провайдеров: sk-…, sk-ant-…, gsk_…, xai-…, AIza…
            '/\b(?:sk|gsk|xai|pk|rk)[-_][A-Za-z0-9_\-]{12,}/' => $tail,
            '/\bAIza[0-9A-Za-z_\-]{20,}/' => $tail,
            // Вебхук Битрикс24: /rest/<пользователь>/<код>/
            '#(/rest/\d+/)([A-Za-z0-9]{6,})#' => static fn (array $m): string => $m[1].self::DOTS,
            // Адрес с логином и паролем: scheme://user:pass@host
            '#(\b[a-z][a-z0-9+.-]*://[^/\s:@]+:)([^@\s/]+)(@)#i' => static fn (array $m): string => $m[1].self::DOTS.$m[3],
            // name=value, name: value, "name":"value" — в query, JSON, тексте
            '/(?<![A-Za-z0-9_])((?:'.self::SECRET_NAMES.'|[A-Za-z0-9_\-]*(?:token|secret|password|api[_-]?key))["\']?\s*[=:]\s*["\']?)([^\s"\'&,;|}\]]{4,})/i'
                => static fn (array $m): string => str_starts_with($m[2], self::DOTS) ? $m[0] : $m[1].self::tail($m[2]),
        ];
        foreach ($rules as $re => $cb) {
            $res = preg_replace_callback($re, $cb, $text);
            if (is_string($res)) {
                $text = $res;
            }
        }

        return $text;
    }

    /**
     * Контекст строки: значения полей с секретными именами — целиком под маску,
     * остальные строки — через string().
     */
    public static function context(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 8) {
            return '…';
        }
        if (is_string($value)) {
            return self::string($value);
        }
        if (!is_array($value)) {
            return is_scalar($value) || $value === null ? $value : get_debug_type($value);
        }
        $out = [];
        foreach ($value as $k => $v) {
            if (is_string($k) && self::isSecretName($k) && $v !== null && $v !== '' && !is_bool($v)) {
                $out[$k] = is_scalar($v) ? self::tail((string)$v) : self::DOTS;
                continue;
            }
            $out[$k] = self::context($v, $depth + 1);
        }

        return $out;
    }
}
