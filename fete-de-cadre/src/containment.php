<?php
/**
 * LAB CONTAINMENT LAYER
 * ---------------------------------------------------------------------------
 * Fête de Cadre is intentionally vulnerable, but every flaw is fenced so it can
 * only be exploited the way the lab designers intended. Server side only; never
 * referenced in the UI.
 *
 *  - XSS sinks: payloads containing a <script> tag or the "alert" keyword are
 *    refused (after entity / escape normalisation). Everything else passes.
 *  - Path traversal: escaping the invoice folder reaches only /etc/passwd and
 *    /etc/hosts.
 *  - OS command injection: only `uname`, `id`, `pwd`, `hostname`, `whoami` can
 *    be chained onto the ping target. Anything else is rejected as an invalid
 *    hostname.
 */
declare(strict_types=1);

/* ------------------------------ XSS filter ------------------------------ */
function xss_normalise(string $value): string {
    $t = $value;
    for ($i = 0; $i < 4; $i++) {
        $before = $t;
        // HTML entities (named + numeric, decimal + hex), with or without trailing ';'
        $t = preg_replace_callback('/&#x([0-9a-f]+);?/i', fn($m) => mb_chr((int) hexdec($m[1]), 'UTF-8'), $t);
        $t = preg_replace_callback('/&#(\d+);?/', fn($m) => mb_chr((int) $m[1], 'UTF-8'), $t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // JS / CSS / URL escape forms
        $t = preg_replace_callback('/\\\\u\{([0-9a-f]+)\}/i', fn($m) => mb_chr((int) hexdec($m[1]), 'UTF-8'), $t);
        $t = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', fn($m) => mb_chr((int) hexdec($m[1]), 'UTF-8'), $t);
        $t = preg_replace_callback('/\\\\x([0-9a-f]{2})/i', fn($m) => mb_chr((int) hexdec($m[1]), 'UTF-8'), $t);
        $t = preg_replace_callback('/%([0-9a-f]{2})/i', fn($m) => chr((int) hexdec($m[1])), $t);
        if ($t === $before) break;
    }
    // strip zero-width / control chars used to split keywords
    $t = preg_replace('/[\x00-\x1f\x7f\x{200b}-\x{200d}\x{2060}\x{feff}]/u', '', $t);
    return mb_strtolower($t, 'UTF-8');
}

/** True when the payload must be blocked (contains a script tag or "alert"). */
function xss_blocked(string $value): bool {
    $t = xss_normalise($value);
    return (bool) preg_match('~<\s*/?\s*script\b~', $t) || str_contains($t, 'alert');
}

/* ---------------------------- Path traversal ---------------------------- */
const TRAVERSAL_TARGETS = ['/etc/passwd', '/etc/hosts'];

/**
 * Resolve a requested invoice file name against the invoice directory.
 * Returns ['inside'=>bool, 'path'=>string] or null when the path is not allowed.
 */
function resolve_download(string $baseDir, string $requested): ?array {
    if ($requested === '' || str_contains($requested, "\0") || strlen($requested) > 300) return null;
    $base = realpath($baseDir);
    if ($base === false) return null;

    // Join relative to base; an absolute requested path keeps its own root.
    if (str_starts_with($requested, '/')) {
        $candidate = $requested;
    } else {
        $candidate = $base . '/' . $requested;
    }
    // Resolve "." / ".." lexically so the decision never depends on whether the
    // target happens to exist on disk (realpath() returns false for missing files).
    $resolved = lexical_resolve($candidate);
    if ($resolved === null) return null;

    if (str_starts_with($resolved, $base . DIRECTORY_SEPARATOR) && is_file($resolved)) {
        return ['inside' => true, 'path' => $resolved];
    }
    if (in_array($resolved, TRAVERSAL_TARGETS, true)) {
        return ['inside' => false, 'path' => $resolved];
    }
    return null;
}

/** Resolve "." and ".." lexically without touching the filesystem. */
function lexical_resolve(string $path): ?string {
    $parts = explode('/', str_replace('\\', '/', $path));
    $stack = [];
    foreach ($parts as $p) {
        if ($p === '' || $p === '.') continue;
        if ($p === '..') { array_pop($stack); continue; }
        $stack[] = $p;
    }
    return '/' . implode('/', $stack);
}

/* ------------------------- OS command injection ------------------------- */
const INJECTABLE_COMMANDS = ['uname', 'id', 'pwd', 'hostname', 'whoami'];
const PING_PREFIX = 'ping -c 2 -W 2';

/**
 * Build the diagnostics shell command from a user-supplied host.
 * Returns ['shell'=>bool, 'command'|'args'] or null when the input is rejected.
 */
function build_ping(string $value): ?array {
    $raw = trim(str_replace("\r", '', $value));
    if ($raw === '' || strlen($raw) > 120) return null;

    $hostRe = '/^[a-zA-Z0-9](?:[a-zA-Z0-9.\-]{0,251}[a-zA-Z0-9])?$/';
    if (preg_match($hostRe, $raw)) {
        return ['shell' => false, 'args' => ['ping', '-c', '2', '-W', '2', $raw]];
    }
    // host <separator> command
    if (preg_match('/^([a-zA-Z0-9.\-]+)\s*(;|&&|\|\||\||&|\n)\s*([^;&|\n`$()<>]+?)\s*$/', $raw, $m)) {
        $cmd = preg_replace('/\s+/', ' ', $m[3]);
        if (preg_match($hostRe, $m[1]) && in_array($cmd, INJECTABLE_COMMANDS, true)) {
            $sep = $m[2] === "\n" ? ';' : $m[2];
            return ['shell' => true, 'command' => PING_PREFIX . " {$m[1]} $sep $cmd"];
        }
        return null;
    }
    // command substitution: host$(cmd) or host`cmd`
    if (preg_match('/^([a-zA-Z0-9.\-]*)(?:\$\(\s*([a-z ]+?)\s*\)|`\s*([a-z ]+?)\s*`)$/', $raw, $m)) {
        $cmd = preg_replace('/\s+/', ' ', $m[2] !== '' ? $m[2] : $m[3]);
        if (in_array($cmd, INJECTABLE_COMMANDS, true)) {
            return ['shell' => true, 'command' => PING_PREFIX . " {$m[1]}\$($cmd)"];
        }
    }
    return null;
}
