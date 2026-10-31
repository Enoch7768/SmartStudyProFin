<?php

declare(strict_types=1);

namespace App\Cms;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;

final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'code', 'dd', 'del', 'div', 'dl', 'dt',
        'em', 'figcaption', 'figure', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr',
        'i', 'img', 'ins', 'li', 'mark', 'ol', 'p', 'pre', 's', 'small', 'span',
        'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'u', 'ul'
    ];

    private const DROP_TAGS = [
        'audio', 'base', 'button', 'embed', 'form', 'iframe', 'input', 'link', 'math',
        'meta', 'object', 'option', 'script', 'select', 'source', 'style', 'svg',
        'template', 'textarea', 'video'
    ];

    public static function clean(string $html): string
    {
        if (strlen($html) > 524288) throw new RuntimeException('Formatted content cannot exceed 512 KB.');
        if ($html === '') return '';
        if (!class_exists(DOMDocument::class)) return self::fallback($html);

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div id="ssp-content-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) return self::fallback($html);
        $root = (new DOMXPath($document))->query('//*[@id="ssp-content-root"]')?->item(0);
        if (!$root) return self::fallback($html);
        self::sanitizeChildren($root);
        $result = '';
        foreach ($root->childNodes as $child) $result .= $document->saveHTML($child);
        return $result;
    }

    private static function fallback(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed|svg|math|template|form|audio|video)\\b[^>]*>.*?</\\1\\s*>#is', '', $html) ?? '';
        return htmlspecialchars(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function sanitizeChildren(DOMNode $parent): void
    {
        for ($index = $parent->childNodes->length - 1; $index >= 0; $index--) {
            $node = $parent->childNodes->item($index);
            if (!$node) continue;
            if ($node->nodeType === XML_COMMENT_NODE || $node->nodeType === XML_PI_NODE) {
                $parent->removeChild($node);
                continue;
            }
            if (!$node instanceof DOMElement) continue;
            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DROP_TAGS, true)) {
                $parent->removeChild($node);
                continue;
            }
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                self::sanitizeChildren($node);
                while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                $parent->removeChild($node);
                continue;
            }
            self::sanitizeAttributes($node, $tag);
            self::sanitizeChildren($node);
        }
    }

    private static function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = ['class', 'title', 'lang', 'dir'];
        if ($tag === 'a') $allowed = array_merge($allowed, ['href', 'target', 'rel']);
        if ($tag === 'img') $allowed = array_merge($allowed, ['src', 'alt', 'width', 'height', 'loading']);
        if ($tag === 'td' || $tag === 'th') $allowed = array_merge($allowed, ['colspan', 'rowspan', 'scope']);
        if ($tag === 'ol') $allowed[] = 'start';
        if ($tag === 'li') $allowed[] = 'value';

        for ($index = $element->attributes->length - 1; $index >= 0; $index--) {
            $attribute = $element->attributes->item($index);
            if (!$attribute) continue;
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);
            if (!in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);
                continue;
            }
            if ($name === 'class' && (strlen($value) > 160 || preg_match('/^[a-zA-Z0-9 _-]*$/', $value) !== 1)) $element->removeAttribute($name);
            if ($name === 'dir' && !in_array(strtolower($value), ['ltr', 'rtl', 'auto'], true)) $element->removeAttribute($name);
            if ($name === 'lang' && preg_match('/^[a-zA-Z0-9-]{1,35}$/', $value) !== 1) $element->removeAttribute($name);
            if (($name === 'href' || $name === 'src') && !self::safeUrl($value, $name === 'href')) $element->removeAttribute($name);
            if (in_array($name, ['width', 'height', 'colspan', 'rowspan', 'start', 'value'], true) && (!ctype_digit($value) || (int) $value > 10000)) $element->removeAttribute($name);
            if ($name === 'target' && !in_array($value, ['_blank', '_self'], true)) $element->removeAttribute($name);
            if ($name === 'loading' && !in_array($value, ['lazy', 'eager'], true)) $element->removeAttribute($name);
            if ($name === 'scope' && !in_array($value, ['row', 'col', 'rowgroup', 'colgroup'], true)) $element->removeAttribute($name);
        }

        if ($tag === 'a' && strtolower($element->getAttribute('target')) === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
        if ($tag === 'img' && !$element->hasAttribute('src')) $element->parentNode?->removeChild($element);
        if ($tag === 'img' && !$element->hasAttribute('alt')) $element->setAttribute('alt', '');
        if ($tag === 'img' && !$element->hasAttribute('loading')) $element->setAttribute('loading', 'lazy');
    }

    private static function safeUrl(string $value, bool $allowMailto): bool
    {
        if ($value === '' || preg_match('/[\x00-\x20\x7f]/', $value)) return false;
        if (str_starts_with($value, '//') || str_contains($value, '\\\\')) return false;
        $scheme = parse_url($value, PHP_URL_SCHEME);
        if ($scheme === null) return true;
        $allowed = $allowMailto ? ['https', 'http', 'mailto', 'tel'] : ['https'];
        return in_array(strtolower((string) $scheme), $allowed, true);
    }
}
