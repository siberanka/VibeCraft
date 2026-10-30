<?php

namespace App\Domain\Content;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class WikiRenderer
{
    /** @return array{html:string,toc:list<array{id:string,label:string,level:int}>} */
    public static function render(string $markdown, ?string $locale = null, string $anchorPrefix = 'section-'): array
    {
        if (! preg_match('/^[a-z][a-z0-9-]{0,80}$/D', $anchorPrefix)) {
            throw new InvalidArgumentException('Invalid document anchor scope.');
        }
        $html = Markdown::render($markdown);
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><div id="wiki-document">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $document->getElementById('wiki-document');
        if (! $root instanceof DOMElement) {
            throw ValidationException::withMessages(['body_markdown' => __('The article could not be formatted.')]);
        }
        $headings = [];
        foreach ($root->getElementsByTagName('*') as $element) {
            if (preg_match('/^h[1-6]$/', $element->tagName)) {
                $headings[] = $element;
            }
        }
        if (count($headings) > 200) {
            throw ValidationException::withMessages(['body_markdown' => __('Use at most 200 headings in one article.')]);
        }
        $used = [];
        $toc = [];
        foreach ($headings as $heading) {
            $level = max(2, (int) substr($heading->tagName, 1));
            if ($heading->tagName === 'h1') {
                $replacement = $document->createElement('h2');
                while ($heading->firstChild !== null) {
                    $replacement->appendChild($heading->firstChild);
                }
                $heading->parentNode?->replaceChild($replacement, $heading);
                $heading = $replacement;
            }
            $label = trim(preg_replace('/\s+/u', ' ', $heading->textContent) ?? '');
            $base = $anchorPrefix.(Str::limit(Str::slug($label), 70, '') ?: 'heading');
            $id = $base;
            $suffix = 2;
            while (isset($used[$id])) {
                $id = $base.'-'.$suffix++;
            }
            $used[$id] = true;
            $heading->setAttribute('id', $id);
            if ($level <= 3 && $label !== '') {
                $toc[] = ['id' => $id, 'label' => Str::limit($label, 160), 'level' => $level];
            }
        }
        $tables = iterator_to_array($root->getElementsByTagName('table'));
        foreach ($tables as $table) {
            $wrapper = $document->createElement('div');
            $wrapper->setAttribute('class', 'table-wrap');
            $wrapper->setAttribute('tabindex', '0');
            $wrapper->setAttribute('role', 'region');
            $wrapper->setAttribute('aria-label', __('Article table', [], $locale));
            $table->parentNode?->replaceChild($wrapper, $table);
            $wrapper->appendChild($table);
        }
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return ['html' => $output, 'toc' => $toc];
    }
}
