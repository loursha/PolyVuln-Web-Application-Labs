/*
 * LAB CONTAINMENT LAYER
 * ---------------------------------------------------------------------------
 * Petal & Stem is intentionally vulnerable, but each flaw is fenced so that it
 * can only be exploited in the way the lab designers intended. This module is
 * server-side only and never referenced in the UI.
 *
 *  - XSS sinks: payloads containing a <script> tag or the "alert" keyword are
 *    refused (after entity / escape normalisation). Everything else passes.
 *  - Path traversal: escaping the invoice folder is only possible to reach two
 *    files: /etc/passwd and /etc/hosts.
 *  - OS command injection: only the commands `id`, `whoami`, `pwd`, `ip a` can
 *    be chained onto the ping target. Anything else is rejected as an invalid
 *    hostname.
 *  - Prototype pollution (lodash 4.17.4): only the `isVip` gadget survives.
 */
const path = require('path');

/* ----------------------------- XSS filter ----------------------------- */
const NAMED = { tab: '\t', newline: '\n', colon: ':', lpar: '(', rpar: ')', lt: '<', gt: '>', quot: '"', apos: "'", amp: '&', sol: '/', bsol: '\\', period: '.', grave: '`' };
const cp = (n) => { try { return String.fromCodePoint(n); } catch { return ''; } };

function normalise(input) {
  let t = String(input);
  for (let i = 0; i < 4; i++) {
    const before = t;
    t = t
      .replace(/&#x([0-9a-f]+);?/gi, (_, h) => cp(parseInt(h, 16)))
      .replace(/&#(\d+);?/g, (_, d) => cp(Number(d)))
      .replace(/&([a-z]+);/gi, (m, n) => (NAMED[n.toLowerCase()] !== undefined ? NAMED[n.toLowerCase()] : m))
      .replace(/\\u\{([0-9a-f]+)\}/gi, (_, h) => cp(parseInt(h, 16)))
      .replace(/\\u([0-9a-f]{4})/gi, (_, h) => cp(parseInt(h, 16)))
      .replace(/\\x([0-9a-f]{2})/gi, (_, h) => cp(parseInt(h, 16)))
      .replace(/%([0-9a-f]{2})/gi, (_, h) => cp(parseInt(h, 16)));
    if (t === before) break;
  }
  return t.replace(/[\u0000-\u001f\u007f\u200b-\u200d\u2060\ufeff]/g, '').toLowerCase();
}

function xssBlocked(input) {
  const t = normalise(input);
  return /<\s*\/?\s*script\b/.test(t) || /alert/.test(t);
}

/* --------------------------- Path traversal --------------------------- */
const TRAVERSAL_TARGETS = new Set(['/etc/passwd', '/etc/hosts']);

function resolveDownload(baseDir, requested) {
  const name = String(requested || '');
  if (!name || name.includes('\0') || name.length > 300) return null;
  const base = path.resolve(baseDir);
  const resolved = path.resolve(base, name);
  if (resolved.startsWith(base + path.sep)) return { inside: true, path: resolved };
  if (TRAVERSAL_TARGETS.has(resolved)) return { inside: false, path: resolved };
  return null;
}

/* ------------------------- OS command injection ----------------------- */
const HOST_RE = /^[a-zA-Z0-9](?:[a-zA-Z0-9.-]{0,251}[a-zA-Z0-9])?$/;
const INJECTABLE = new Set(['id', 'whoami', 'pwd', 'ip a']);
const PING = 'ping -c 2 -W 2';

function buildPing(input) {
  const raw = String(input || '').replace(/\r/g, '').trim();
  if (!raw || raw.length > 120) return null;
  if (HOST_RE.test(raw)) return { shell: false, args: ['-c', '2', '-W', '2', raw] };

  // host <separator> command
  let m = raw.match(/^([a-zA-Z0-9.-]+)\s*(;|&&|\|\||\||&|\n)\s*([^;&|\n`$()<>]+?)\s*$/);
  if (m) {
    const cmd = m[3].replace(/\s+/g, ' ');
    if (HOST_RE.test(m[1]) && INJECTABLE.has(cmd)) {
      const sep = m[2] === '\n' ? ';' : m[2];
      return { shell: true, command: `${PING} ${m[1]} ${sep} ${cmd}` };
    }
    return null;
  }
  // command substitution: host$(cmd) or host`cmd`
  m = raw.match(/^([a-zA-Z0-9.-]*)(?:\$\(\s*([a-z ]+?)\s*\)|`\s*([a-z ]+?)\s*`)$/);
  if (m) {
    const cmd = (m[2] || m[3]).replace(/\s+/g, ' ');
    if ((m[1] === '' || /^[a-zA-Z0-9.-]+$/.test(m[1])) && INJECTABLE.has(cmd)) {
      return { shell: true, command: `${PING} ${m[1]}$(${cmd})` };
    }
  }
  return null;
}

/* ------------------------ Prototype pollution ------------------------- */
const POLLUTION_ALLOWED = new Set(['isVip']);
const GUARDED = [Object, Object.prototype, Array.prototype, Function.prototype, String.prototype, Number.prototype, Boolean.prototype];
const SNAPSHOT = GUARDED.map(o => ({ o, desc: Object.getOwnPropertyDescriptors(o) }));

function enforcePrototypes() {
  for (const { o, desc } of SNAPSHOT) {
    for (const k of Reflect.ownKeys(o)) {
      if (!(k in desc) && !(o === Object.prototype && POLLUTION_ALLOWED.has(k))) delete o[k];
    }
    for (const k of Reflect.ownKeys(desc)) {
      const cur = Object.getOwnPropertyDescriptor(o, k);
      if (!cur || cur.value !== desc[k].value || cur.get !== desc[k].get || cur.set !== desc[k].set) {
        try { Object.defineProperty(o, k, desc[k]); } catch { /* non-configurable */ }
      }
    }
  }
}

function onlyGadget(v) {
  const out = {};
  if (v && typeof v === 'object' && Object.prototype.hasOwnProperty.call(v, 'isVip')) out.isVip = v.isVip === true || v.isVip === 'true';
  return out;
}

// Reduce any prototype-reaching branch of a JSON body to the isVip gadget only.
function containPollution(obj, depth = 0) {
  if (!obj || typeof obj !== 'object' || depth > 6) return obj;
  for (const k of Object.keys(obj)) {
    const v = obj[k];
    if (k === '__proto__' || k === 'prototype') {
      Object.defineProperty(obj, k, { value: onlyGadget(v), enumerable: true, writable: true, configurable: true });
    } else if (k === 'constructor') {
      const proto = v && typeof v === 'object' && Object.prototype.hasOwnProperty.call(v, 'prototype') ? onlyGadget(v.prototype) : null;
      if (proto) obj[k] = { prototype: proto }; else delete obj[k];
    } else if (v && typeof v === 'object') {
      containPollution(v, depth + 1);
    }
  }
  return obj;
}

const resetPollution = () => { POLLUTION_ALLOWED.forEach(k => { delete Object.prototype[k]; }); };

module.exports = { xssBlocked, resolveDownload, buildPing, enforcePrototypes, containPollution, resetPollution };
