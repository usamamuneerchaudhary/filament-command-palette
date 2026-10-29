<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Labels that originate as Htmlable must never be rendered raw. Filament
 * global-search titles often wrap a badge in a span; everything else is
 * stripped.
 */
class SafeHtml
{
    public static function label(string $html): string
    {
        if (class_exists(HtmlSanitizer::class) && class_exists(HtmlSanitizerConfig::class)) {
            $config = (new HtmlSanitizerConfig)
                ->allowElement('span', ['class'])
                ->allowElement('b')
                ->allowElement('strong')
                ->allowElement('em')
                ->allowElement('i')
                ->allowElement('small')
                ->allowElement('mark', ['class'])
                ->allowElement('code')
                ->allowElement('br');

            return (new HtmlSanitizer($config))->sanitize($html);
        }

        $html = (string) preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
        $stripped = strip_tags($html, '<span><b><strong><em><i><small><mark><code><br>');

        return (string) preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $stripped);
    }
}
