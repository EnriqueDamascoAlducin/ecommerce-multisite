<?php

namespace App\Domain\Storefront;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist sanitizer for the rich-text (TipTap) HTML stored on sections.
 *
 * Keeps only the tags TipTap's StarterKit produces, drops every attribute
 * except a validated href on links, and removes script/style/comment nodes.
 * This prevents stored XSS even though page editors are trusted admins.
 */
class HtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'span', 'mark',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'ul', 'ol', 'li',
        'blockquote', 'pre', 'code',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'a',
    ];

    /** @var list<string> CSS color keywords accepted in inline styles. */
    private const COLOR_KEYWORDS = [
        'black', 'white', 'red', 'green', 'blue', 'yellow', 'orange',
        'purple', 'pink', 'gray', 'grey', 'transparent', 'currentcolor',
    ];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementsByTagName('div')->item(0);

        if (! $root instanceof DOMElement) {
            return '';
        }

        self::sanitizeChildren($root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    private static function sanitizeChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, ['script', 'style'], true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                self::unwrap($child);

                continue;
            }

            self::stripAttributes($child, $tag);
            self::sanitizeChildren($child);
        }
    }

    /**
     * Drop a disallowed element but keep its (sanitized) children in place.
     */
    private static function unwrap(DOMElement $element): void
    {
        self::sanitizeChildren($element);

        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private static function stripAttributes(DOMElement $element, string $tag): void
    {
        /** @var array<string, string> $keep */
        $keep = [];

        if ($tag === 'a') {
            $href = $element->getAttribute('href');

            if (self::isSafeHref($href)) {
                $keep = [
                    'href' => $href,
                    'rel' => 'noopener nofollow',
                    'target' => '_blank',
                ];
            }
        }

        // Keep a validated inline color style (text color and highlight),
        // produced by the rich-text editor's Color/Highlight marks.
        $style = self::sanitizeStyle($element->getAttribute('style'));

        if ($style !== '') {
            $keep['style'] = $style;
        }

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->nodeName);
        }

        foreach ($keep as $name => $value) {
            $element->setAttribute($name, $value);
        }
    }

    /**
     * Keep only `color` / `background-color` declarations with a safe value.
     */
    private static function sanitizeStyle(string $style): string
    {
        if (trim($style) === '') {
            return '';
        }

        $safe = [];

        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = explode(':', $declaration, 2);
            $property = strtolower(trim($property));
            $value = trim($value);

            if (! in_array($property, ['color', 'background-color'], true)) {
                continue;
            }

            if (self::isSafeColor($value)) {
                $safe[] = $property.': '.$value;
            }
        }

        return implode('; ', $safe);
    }

    private static function isSafeColor(string $value): bool
    {
        if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value) === 1) {
            return true;
        }

        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/i', $value) === 1) {
            return true;
        }

        return in_array(strtolower($value), self::COLOR_KEYWORDS, true);
    }

    private static function isSafeHref(string $href): bool
    {
        $href = trim($href);

        if ($href === '') {
            return false;
        }

        if (str_starts_with($href, '/') || str_starts_with($href, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }
}
