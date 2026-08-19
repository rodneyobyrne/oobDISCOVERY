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
:root{--ink:#0b0b0b;--paper:#fff;--soft:#f1f0ec;--line:#c9c7c0;--muted:#5d5d5d;--accent:#d99f6c;--warm:#f8f4ee;--warm-line:#ded4c8;color-scheme:light}
*{box-sizing:border-box}html{font-family:Arial,Helvetica,sans-serif;background:var(--soft);color:var(--ink)}body{margin:0;font-size:16px;line-height:1.6}a{color:inherit;text-underline-offset:.18em}button,input,select{font:inherit}:focus-visible{outline:3px solid var(--accent);outline-offset:3px}
.account-header{position:sticky;z-index:1000;top:0;background:#fff;color:#111}.account-header.private{background:#111;color:#fff}.account-header::after{content:"";position:absolute;right:0;bottom:-5px;left:0;height:10px;background:#111;clip-path:polygon(0 34%,8% 27%,17% 42%,28% 30%,40% 42%,52% 29%,64% 42%,77% 29%,89% 41%,100% 34%,100% 67%,89% 60%,77% 71%,64% 61%,52% 71%,40% 61%,28% 72%,16% 60%,0 68%)}.account-header.private::after{background:#fff}.account-header-inner{width:min(1180px,calc(100% - 2rem));min-height:104px;margin:auto;display:flex;align-items:center;justify-content:space-between;gap:2rem}.account-brand{display:inline-flex;align-items:center;gap:1rem;text-decoration:none}.account-brand img{display:block;width:auto;height:82px;object-fit:contain}.account-header.private .account-brand img{filter:invert(1)}.brand-copy{display:grid;line-height:1}.brand-name{font-size:1rem;font-weight:700;letter-spacing:-.04em}.brand-name span{color:#929292;font-weight:400}.brand-sub{margin-top:.45rem;font-size:.65rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase}.account-header-actions{display:flex;align-items:center;justify-content:flex-end;gap:.65rem;flex-wrap:wrap}.header-context{font-size:.76rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase}.account-header.private .button,.account-header.private button{border-color:#fff}.account-header.private .button-secondary{background:#fff;color:#111}.invitation-existing-link{font-size:.86rem;font-weight:700;text-decoration:none;border-bottom:1px solid #111;padding:.2rem 0}.invitation-existing-link strong{font-weight:800}
.shell{width:min(100% - 2rem,700px);margin:clamp(1rem,5vw,4rem) auto;background:var(--paper);border-top:7px solid var(--ink);padding:clamp(1.5rem,6vw,4rem);box-shadow:14px 14px 0 rgba(0,0,0,.07)}.eyebrow{margin:0 0 .5rem;font-size:.76rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}h1{margin:0;font-size:clamp(2.45rem,9vw,4.8rem);line-height:.94;letter-spacing:-.06em}.lede{max-width:38rem;margin:1rem 0 0;color:#363636;font-size:1.05rem}.rule{height:5px;background:var(--ink);margin:2rem -.35rem;transform:rotate(-.25deg)}
.form{display:grid;gap:.45rem}.form label{font-weight:800;margin-top:.65rem}.form input,.form select{width:100%;min-height:48px;border:1px solid #686868;border-radius:0;background:#fff;padding:.65rem}.form small,.help{color:var(--muted);font-size:.86rem}.button,button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;border:1px solid var(--ink);border-radius:0;background:var(--ink);color:#fff;padding:.7rem 1rem;font-weight:800;text-decoration:none;cursor:pointer}.form button{margin-top:1rem}.button:hover,button:hover{background:#333}.button-secondary{background:#fff;color:var(--ink)}.button-secondary:hover{background:var(--soft)}.actions{display:flex;flex-wrap:wrap;gap:.65rem;margin-top:1.5rem}.notice{padding:.85rem 1rem;border-left:5px solid;margin:1.25rem 0}.notice-error{background:#fff0ed;border-color:#ad2f1b}.notice-success{background:#edf8ed;border-color:#24712b}.notice-info{background:var(--soft);border-color:var(--ink)}.privacy{font-size:.82rem;color:var(--muted);margin:2rem 0 0}.split{display:grid;grid-template-columns:1fr 1fr;gap:2rem}.card{border-top:3px solid var(--ink);padding-top:1rem}.card h2{margin:.25rem 0;font-size:1.45rem;letter-spacing:-.03em}.mono{overflow-wrap:anywhere;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;background:#111;color:#fff;padding:1rem}.list{list-style:none;padding:0;margin:1.5rem 0}.list li{border-top:1px solid var(--line);padding:1rem 0}.meta{color:var(--muted);font-size:.86rem}.inline{display:flex;align-items:end;gap:.65rem}.inline>*{flex:1}.inline button{flex:0 0 auto}.toplink{display:inline-block;margin-bottom:1rem;font-weight:800}
.shell:has(.invitation-experience){width:min(100% - 2rem,960px);padding:clamp(2rem,5vw,4.5rem);box-shadow:18px 18px 0 rgba(0,0,0,.06)}.shell:has(.invitation-experience) h1{max-width:48rem;font-size:clamp(3rem,7vw,5.4rem)}.shell:has(.invitation-experience) .lede{max-width:46rem;font-size:1.12rem;line-height:1.65}.invitation-experience{display:grid;gap:2rem}.invitation-assurance{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--warm-line);border:1px solid var(--warm-line)}.assurance-item{background:var(--warm);padding:1.15rem}.assurance-item strong{display:block;margin-bottom:.2rem;font-size:.9rem}.assurance-item span{display:block;color:#47433f;font-size:.84rem;line-height:1.45}.invitation-note{display:flex;align-items:flex-start;gap:.75rem;margin:0;padding:1rem 1.1rem;background:#fff;border-left:4px solid var(--ink);font-size:.9rem}.invitation-note::before{content:"✓";font-weight:900}.primary-account-card{max-width:720px;padding:clamp(1.4rem,4vw,2.2rem);background:var(--warm);border:1px solid var(--warm-line)}.primary-account-card h2{margin:.2rem 0 .45rem;font-size:clamp(1.8rem,4vw,2.7rem);line-height:1.05;letter-spacing:-.045em}.primary-account-card .help{max-width:38rem;margin:.35rem 0 1rem;font-size:.94rem;line-height:1.55;color:#45413c}.primary-account-card .form{gap:.6rem}.primary-account-card .form input{min-height:54px;background:#fff}.password-pair{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.next-step{margin:.8rem 0 0;color:#4c4944;font-size:.84rem}.existing-account{border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:.2rem 0}.existing-account summary{cursor:pointer;list-style:none;padding:1rem 0;font-weight:800}.existing-account summary::-webkit-details-marker{display:none}.existing-account summary::after{content:"+";float:right;font-size:1.2rem}.existing-account[open] summary::after{content:"–"}.existing-account .existing-inner{max-width:720px;padding:0 0 1.4rem}.existing-account h2{margin:.15rem 0 .35rem;font-size:1.35rem}.existing-account .form{grid-template-columns:1fr 1fr auto;align-items:end;gap:.75rem}.existing-account .form .field{display:grid;gap:.3rem}.existing-account .form label{margin:0}.existing-account .form button{margin:0;min-height:48px}.existing-help{margin:.6rem 0 0;font-size:.84rem;color:var(--muted)}
@media(max-width:760px){.account-header-inner{align-items:flex-start;flex-direction:column;padding:1rem 0}.account-brand img{height:68px}.account-header-actions{width:100%;justify-content:flex-start}.split{grid-template-columns:1fr}.inline{align-items:stretch;flex-direction:column}.inline button{width:100%}.shell{box-shadow:none}.invitation-assurance{grid-template-columns:1fr}.password-pair{grid-template-columns:1fr}.existing-account .form{grid-template-columns:1fr}.existing-account .form button{width:100%}}
CSS;
}

function oobRenderAccountPage(string $title, string $eyebrow, string $lede, string $body, int $status = 200, string $headerActions = '', bool $privateHeader = false): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $privateHeader = $privateHeader || strcasecmp(trim($eyebrow), 'Full Admin') === 0;
    if ($privateHeader && $headerActions === '') {
        $csrf = function_exists('oobCsrfToken') ? oobEscape(oobCsrfToken()) : '';
        $headerActions = '<a class="button" href="/discovery/results/">Responses</a><a class="button button-secondary" href="/discovery/results/invitations/">Projects, users &amp; access</a>';
        if ($csrf !== '') {
            $headerActions .= '<form method="post" action="/discovery/results/"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="logout"><button type="submit">Sign out</button></form>';
        }
    }
    $headerClass = $privateHeader ? 'account-header private' : 'account-header';
    $context = $headerActions !== '' ? $headerActions : '<span class="header-context">Private account</span>';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . oobEscape($title) . ' · oobCREATIVE</title><style>' . oobAccountCss() . '</style></head><body>';
    echo '<header class="' . $headerClass . '"><div class="account-header-inner"><a class="account-brand" href="https://discovery.oobcreative.com/" aria-label="oobCREATIVE Discovery home"><img src="https://skills.oobcreative.com/branding/Mark-black.svg" alt=""><span class="brand-copy"><span class="brand-name"><span>oob</span>CREATIVE</span><span class="brand-sub">Discovery</span></span></a><div class="account-header-actions">' . $context . '</div></div></header>';
    echo '<main class="shell"><p class="eyebrow">' . oobEscape($eyebrow) . '</p><h1>' . oobEscape($title) . '</h1><p class="lede">' . oobEscape($lede) . '</p><div class="rule"></div>' . $body . '<p class="privacy">Private project access. Do not enter patient-identifying information, medical records, or other regulated personal data.</p></main></body></html>';
    exit;
}
