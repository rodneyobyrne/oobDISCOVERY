<?php
declare(strict_types=1);

function oobApplySecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
    header('Vary: Cookie');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header("Content-Security-Policy: default-src 'none'; img-src 'self' https://skills.oobcreative.com; style-src 'self' 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
}

function oobEscape(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function oobRedirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

function oobIsSecureRequest(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function oobAccountCss(): string
{
    return <<<'CSS'
:root{--ink:#0b0b0b;--paper:#fff;--soft:#f1f0ec;--line:#c9c7c0;--muted:#5d5d5d;--accent:#d99f6c;color-scheme:light}
*{box-sizing:border-box}html{font-family:Arial,Helvetica,sans-serif;background:var(--soft);color:var(--ink)}body{margin:0;font-size:16px;line-height:1.6}a{color:inherit;text-underline-offset:.18em}button,input,select{font:inherit}:focus-visible{outline:3px solid var(--accent);outline-offset:3px}
.shell{width:min(100% - 2rem,700px);margin:clamp(1rem,5vw,4rem) auto;background:var(--paper);border-top:7px solid var(--ink);padding:clamp(1.5rem,6vw,4rem);box-shadow:14px 14px 0 rgba(0,0,0,.07)}.mark{width:48px;height:48px;object-fit:contain;margin-bottom:2rem}.eyebrow{margin:0 0 .5rem;font-size:.76rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}h1{margin:0;font-size:clamp(2.45rem,9vw,4.8rem);line-height:.94;letter-spacing:-.06em}.lede{max-width:38rem;margin:1rem 0 0;color:#363636;font-size:1.05rem}.rule{height:5px;background:var(--ink);margin:2rem -.35rem;transform:rotate(-.25deg)}
.form{display:grid;gap:.45rem}.form label{font-weight:800;margin-top:.65rem}.form input,.form select{width:100%;min-height:48px;border:1px solid #686868;border-radius:0;background:#fff;padding:.65rem}.form small,.help{color:var(--muted);font-size:.86rem}.button,button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;border:1px solid var(--ink);border-radius:0;background:var(--ink);color:#fff;padding:.7rem 1rem;font-weight:800;text-decoration:none;cursor:pointer}.form button{margin-top:1rem}.button:hover,button:hover{background:#333}.button-secondary{background:#fff;color:var(--ink)}.button-secondary:hover{background:var(--soft)}.actions{display:flex;flex-wrap:wrap;gap:.65rem;margin-top:1.5rem}.notice{padding:.85rem 1rem;border-left:5px solid;margin:1.25rem 0}.notice-error{background:#fff0ed;border-color:#ad2f1b}.notice-success{background:#edf8ed;border-color:#24712b}.notice-info{background:var(--soft);border-color:var(--ink)}.privacy{font-size:.82rem;color:var(--muted);margin:2rem 0 0}.split{display:grid;grid-template-columns:1fr 1fr;gap:2rem}.card{border-top:3px solid var(--ink);padding-top:1rem}.card h2{margin:.25rem 0;font-size:1.45rem;letter-spacing:-.03em}.mono{overflow-wrap:anywhere;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;background:#111;color:#fff;padding:1rem}.list{list-style:none;padding:0;margin:1.5rem 0}.list li{border-top:1px solid var(--line);padding:1rem 0}.meta{color:var(--muted);font-size:.86rem}.inline{display:flex;align-items:end;gap:.65rem}.inline>*{flex:1}.inline button{flex:0 0 auto}.toplink{display:inline-block;margin-bottom:1rem;font-weight:800}
@media(max-width:640px){.split{grid-template-columns:1fr}.inline{align-items:stretch;flex-direction:column}.inline button{width:100%}.shell{box-shadow:none}}
CSS;
}

function oobRenderAccountPage(string $title, string $eyebrow, string $lede, string $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . oobEscape($title) . ' · oobCREATIVE</title><style>' . oobAccountCss() . '</style></head><body>';
    echo '<main class="shell"><img class="mark" src="https://skills.oobcreative.com/branding/Mark-black.svg" alt="oobCREATIVE"><p class="eyebrow">' . oobEscape($eyebrow) . '</p><h1>' . oobEscape($title) . '</h1><p class="lede">' . oobEscape($lede) . '</p><div class="rule"></div>' . $body . '<p class="privacy">Private research access. Never enter patient-identifying information or medical records.</p></main></body></html>';
    exit;
}
