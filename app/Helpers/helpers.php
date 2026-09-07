<?php

use App\Core\Session;
use App\Core\Url;

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return Url::to($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url($path);
    }
}

if (!function_exists('e')) {
    function e(?string $string): string
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        Session::start();

        $token = Session::get('csrf_token');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set('csrf_token', $token);
        }

        return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(): bool
    {
        Session::start();

        $token = $_POST['csrf_token'] ?? null;
        $sessionToken = Session::get('csrf_token');

        if (!is_string($token) || !is_string($sessionToken) || $token === '' || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return $_POST[$key] ?? $default;
    }
}

if (!function_exists('video_embed_url')) {
    /**
     * Converte URLs comuns de YouTube/Vimeo em URLs de embed HTTPS permitidas.
     * Retorna null para hosts/esquemas não autorizados.
     */
    function video_embed_url(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || strlen($value) > 1000) {
            return null;
        }

        $parts = parse_url($value);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if ($scheme !== 'https') {
            return null;
        }

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            $videoId = '';
            if (preg_match('#^/embed/([A-Za-z0-9_-]{6,20})#', $path, $m)) {
                $videoId = $m[1];
            } elseif ($path === '/watch') {
                parse_str((string) ($parts['query'] ?? ''), $query);
                $candidate = $query['v'] ?? '';
                if (is_string($candidate) && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $candidate)) {
                    $videoId = $candidate;
                }
            }

            return $videoId !== ''
                ? 'https://www.youtube-nocookie.com/embed/' . $videoId
                : null;
        }

        if ($host === 'youtu.be') {
            $videoId = trim($path, '/');
            return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId)
                ? 'https://www.youtube-nocookie.com/embed/' . $videoId
                : null;
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true)) {
            $videoId = trim($path, '/');
            return preg_match('/^[0-9]{5,20}$/', $videoId)
                ? 'https://player.vimeo.com/video/' . $videoId
                : null;
        }

        if ($host === 'player.vimeo.com' && preg_match('#^/video/([0-9]{5,20})#', $path, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return null;
    }
}

if (!function_exists('safe_lesson_html')) {
    /**
     * Renderiza o HTML didático com uma allowlist conservadora.
     * Sem ext-dom, prioriza segurança e mostra o conteúdo como texto.
     */
    function safe_lesson_html(mixed $value): string
    {
        $html = is_string($value) ? $value : '';
        if ($html === '') {
            return '<p>Conteúdo desta aula em breve.</p>';
        }

        if (!class_exists('DOMDocument')) {
            return nl2br(e($html));
        }

        $allowedTags = [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li',
            'h2', 'h3', 'h4', 'blockquote', 'pre', 'code', 'a', 'span', 'hr',
        ];
        $dropEntirely = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math'];

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<div id="__lesson_root__">' . $html . '</div>';
        $doc->loadHTML(
            '<?xml encoding="UTF-8">' . $wrapped,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $sanitize = function (\DOMNode $node) use (&$sanitize, $allowedTags, $dropEntirely): void {
            for ($child = $node->firstChild; $child !== null;) {
                $next = $child->nextSibling;

                if ($child instanceof \DOMComment) {
                    $node->removeChild($child);
                    $child = $next;
                    continue;
                }

                if ($child instanceof \DOMElement) {
                    $tag = strtolower($child->tagName);

                    if (in_array($tag, $dropEntirely, true)) {
                        $node->removeChild($child);
                        $child = $next;
                        continue;
                    }

                    if (!in_array($tag, $allowedTags, true)) {
                        while ($child->firstChild) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                        $child = $next;
                        continue;
                    }

                    $allowedAttrs = $tag === 'a' ? ['href', 'title', 'target', 'rel'] : [];
                    for ($i = $child->attributes->length - 1; $i >= 0; $i--) {
                        $attr = $child->attributes->item($i);
                        if ($attr && !in_array(strtolower($attr->name), $allowedAttrs, true)) {
                            $child->removeAttribute($attr->name);
                        }
                    }

                    if ($tag === 'a' && $child->hasAttribute('href')) {
                        $href = trim($child->getAttribute('href'));
                        $safeHref = str_starts_with($href, '/') || str_starts_with($href, '#');
                        if (!$safeHref) {
                            $hrefParts = parse_url($href);
                            $hrefScheme = is_array($hrefParts)
                                ? strtolower((string) ($hrefParts['scheme'] ?? ''))
                                : '';
                            $safeHref = in_array($hrefScheme, ['http', 'https', 'mailto'], true);
                        }
                        if (!$safeHref) {
                            $child->removeAttribute('href');
                        }
                    }

                    if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                        $child->setAttribute('rel', 'noopener noreferrer');
                    }

                    $sanitize($child);
                }

                $child = $next;
            }
        };

        $root = $doc->getElementById('__lesson_root__');
        if (!$root) {
            return nl2br(e($html));
        }

        $sanitize($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        return $output;
    }
}
