<?php

namespace App\Domain\Content;

use Illuminate\Support\Str;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;

/**
 * Markdown → HTML for staff-authored content. Raw HTML is escaped, unsafe link
 * schemes (javascript:, data:, vbscript:, file:) are removed and external links get
 * rel="nofollow noopener noreferrer". Images are limited to uploaded media and bundled
 * assets (InertImageExtension). The result is cached in the *_html column.
 */
final class Markdown
{
    public static function render(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return (string) Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'external_link' => [
                'internal_hosts' => [$host],
                'open_in_new_window' => false,
                'nofollow' => 'external',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ], [new ExternalLinkExtension, new InertImageExtension($host)]);
    }
}
