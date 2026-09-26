#!/usr/bin/env node
/*
 * Checks that a Vite build is complete before it is deployed.
 *
 *   node scripts/verify-vite-build.mjs [publicDir]     (default: public)
 *
 * public/build is generated and never committed (.gitignore), and Azure never
 * builds (SCM_DO_BUILD_DURING_DEPLOYMENT=false), so whatever build a release
 * carries is the only one the live site has. A manifest pointing at CSS that
 * never shipped is exactly what an earlier half-committed public/build left on
 * main - this makes that a failed deploy instead of an unstyled site.
 *
 * Checked, collecting every failure before exiting 1:
 *   - manifest.json exists, parses, and is a non-empty object;
 *   - the three Vite inputs (vite.config.js) are present as entries;
 *   - every entry's file / css[] / assets[] is a real file inside build/, and
 *     every imports[] / dynamicImports[] names an existing manifest key;
 *   - every url() in every manifest-referenced CSS file resolves. Fonts pulled
 *     in by app.css are top-level manifest keys, not listed under the app.css
 *     entry's assets, so the manifest alone cannot prove the CSS works;
 *   - Acumin Pro, the site typeface: the storefront CSS references all four
 *     faces in both WOFF2 and OTF, and the admin panel's font stylesheet
 *     (public/fonts/filament/filament/acumin-pro/index.css, loaded by
 *     AdminPanelProvider's LocalFontProvider, not through Vite) resolves too.
 *
 * Node built-ins only, so it runs on the CI runner, on a laptop before a
 * manual upload, and against test fixtures without an npm install.
 */

import fs from 'node:fs';
import path from 'node:path';

const REQUIRED_ENTRIES = [
    'resources/css/app.css',
    'resources/css/filament/admin/theme.css',
    'resources/js/app.js',
];

const STOREFRONT_CSS_ENTRY = 'resources/css/app.css';
const ACUMIN_FACES = ['RPro', 'ItPro', 'BdPro', 'BdItPro'];
const ACUMIN_FORMATS = ['woff2', 'otf'];
const ADMIN_FONT_CSS = 'fonts/filament/filament/acumin-pro/index.css';

// Vite's default base for laravel-vite-plugin: URLs in built CSS look like
// /build/assets/foo-hash.woff2.
const BUILD_URL_PREFIX = '/build/';

const publicDir = path.resolve(process.argv[2] ?? 'public');
const buildDir = path.join(publicDir, 'build');
const manifestPath = path.join(buildDir, 'manifest.json');

const errors = [];
const confirmed = [];

const fail = (message) => errors.push(message);

const isInside = (parent, child) => {
    const relative = path.relative(parent, child);

    return relative !== '' && ! relative.startsWith('..') && ! path.isAbsolute(relative);
};

const isFile = (target) => {
    try {
        return fs.statSync(target).isFile();
    } catch {
        return false;
    }
};

const display = (target) => path.relative(publicDir, target).split(path.sep).join('/');

/**
 * Resolves a manifest path (relative to build/) and reports if it escapes
 * build/ or is not a file. Returns the absolute path when it is usable.
 */
function checkBuildFile(relative, where) {
    if (typeof relative !== 'string' || relative === '') {
        fail(`${where}: expected a non-empty path string, got ${JSON.stringify(relative)}.`);

        return null;
    }

    if (path.isAbsolute(relative) || /^[a-z]+:/i.test(relative)) {
        fail(`${where}: "${relative}" must be relative to public/build.`);

        return null;
    }

    const absolute = path.resolve(buildDir, relative);

    if (! isInside(buildDir, absolute)) {
        fail(`${where}: "${relative}" escapes public/build.`);

        return null;
    }

    if (! isFile(absolute)) {
        fail(`${where}: "${relative}" is missing (expected ${display(absolute)}).`);

        return null;
    }

    return absolute;
}

/** Every url(...) target in a stylesheet, quotes stripped. */
function cssUrls(css) {
    const urls = [];
    const pattern = /url\(\s*(['"]?)(.*?)\1\s*\)/g;
    let match;

    while ((match = pattern.exec(css)) !== null) {
        urls.push(match[2].trim());
    }

    return urls;
}

const isExternalUrl = (url) => url === ''
    || url.startsWith('#')
    || url.startsWith('//')
    || /^(data|https?):/i.test(url);

/**
 * Resolves each local url() in a stylesheet and reports missing targets.
 * Root-relative URLs are resolved against public/, relative ones against the
 * stylesheet itself; either way the target must stay inside `allowedRoot`.
 * Returns the resolved targets.
 */
function checkCssUrls(cssPath, allowedRoot, allowedLabel) {
    const resolved = [];
    const css = fs.readFileSync(cssPath, 'utf8');

    for (const raw of cssUrls(css)) {
        if (isExternalUrl(raw)) {
            continue;
        }

        const url = raw.split(/[?#]/)[0];

        const target = url.startsWith('/')
            ? path.resolve(publicDir, '.' + url)
            : path.resolve(path.dirname(cssPath), url);

        if (url.startsWith('/') && allowedRoot === buildDir && ! url.startsWith(BUILD_URL_PREFIX)) {
            fail(`${display(cssPath)}: url(${raw}) points outside ${BUILD_URL_PREFIX}.`);
            continue;
        }

        if (! isInside(allowedRoot, target)) {
            fail(`${display(cssPath)}: url(${raw}) escapes ${allowedLabel}.`);
            continue;
        }

        if (! isFile(target)) {
            fail(`${display(cssPath)}: url(${raw}) is missing (expected ${display(target)}).`);
            continue;
        }

        resolved.push(target);
    }

    return resolved;
}

function readManifest() {
    if (! isFile(manifestPath)) {
        fail(`Manifest not found at ${manifestPath}. Run "npm run build" first.`);

        return null;
    }

    let manifest;

    try {
        manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
    } catch (error) {
        fail(`Manifest is not valid JSON: ${error.message}`);

        return null;
    }

    if (manifest === null || typeof manifest !== 'object' || Array.isArray(manifest)) {
        fail('Manifest must be a JSON object keyed by source path.');

        return null;
    }

    if (Object.keys(manifest).length === 0) {
        fail('Manifest is empty - the build produced nothing.');

        return null;
    }

    return manifest;
}

function checkManifest(manifest) {
    for (const key of REQUIRED_ENTRIES) {
        if (! Object.hasOwn(manifest, key)) {
            fail(`Required entry "${key}" is missing from the manifest.`);
        } else if (manifest[key]?.isEntry !== true) {
            fail(`"${key}" is in the manifest but is not marked as an entry.`);
        }
    }

    const cssFiles = new Map();

    for (const [key, chunk] of Object.entries(manifest)) {
        if (chunk === null || typeof chunk !== 'object' || Array.isArray(chunk)) {
            fail(`Manifest entry "${key}" is malformed (expected an object).`);
            continue;
        }

        const file = checkBuildFile(chunk.file, `"${key}".file`);

        if (file && file.endsWith('.css')) {
            cssFiles.set(file, key);
        }

        for (const field of ['css', 'assets']) {
            if (chunk[field] === undefined) {
                continue;
            }

            if (! Array.isArray(chunk[field])) {
                fail(`"${key}".${field} is malformed (expected an array).`);
                continue;
            }

            chunk[field].forEach((relative, index) => {
                const absolute = checkBuildFile(relative, `"${key}".${field}[${index}]`);

                if (absolute && absolute.endsWith('.css')) {
                    cssFiles.set(absolute, key);
                }
            });
        }

        for (const field of ['imports', 'dynamicImports']) {
            if (chunk[field] === undefined) {
                continue;
            }

            if (! Array.isArray(chunk[field])) {
                fail(`"${key}".${field} is malformed (expected an array).`);
                continue;
            }

            chunk[field].forEach((target, index) => {
                if (typeof target !== 'string' || ! Object.hasOwn(manifest, target)) {
                    fail(`"${key}".${field}[${index}] names ${JSON.stringify(target)}, which is not a manifest key.`);
                } else {
                    confirmed.push(`${field}: ${key} -> ${target}`);
                }
            });
        }
    }

    const storefrontFonts = [];

    for (const [cssPath, key] of cssFiles) {
        const targets = checkCssUrls(cssPath, buildDir, 'public/build');

        confirmed.push(`${key} -> ${display(cssPath)} (${targets.length} url() targets present)`);

        if (key === STOREFRONT_CSS_ENTRY) {
            storefrontFonts.push(...targets.map((target) => path.basename(target)));
        }
    }

    for (const face of ACUMIN_FACES) {
        for (const format of ACUMIN_FORMATS) {
            const pattern = new RegExp(`^Acumin-${face}-[^.]+\\.${format}$`);
            const found = storefrontFonts.find((name) => pattern.test(name));

            if (found) {
                confirmed.push(`Acumin Pro (storefront): ${found}`);
            } else {
                fail(`Storefront CSS has no present Acumin-${face} .${format} font (see @font-face in resources/css/app.css).`);
            }
        }
    }

    for (const key of REQUIRED_ENTRIES) {
        if (manifest[key]?.file) {
            confirmed.push(`entry ${key} -> build/${manifest[key].file}`);
        }
    }
}

function checkAdminFonts() {
    const cssPath = path.join(publicDir, ADMIN_FONT_CSS);

    if (! isFile(cssPath)) {
        fail(`Admin font stylesheet missing: ${ADMIN_FONT_CSS} (loaded by AdminPanelProvider's LocalFontProvider).`);

        return;
    }

    const fontDir = path.dirname(cssPath);
    const targets = checkCssUrls(cssPath, fontDir, path.dirname(ADMIN_FONT_CSS));

    if (targets.length === 0) {
        fail(`${ADMIN_FONT_CSS} references no font files.`);
    }

    for (const target of targets) {
        confirmed.push(`Acumin Pro (admin): ${display(target)}`);
    }
}

const manifest = readManifest();

if (manifest) {
    checkManifest(manifest);
}

checkAdminFonts();

if (errors.length > 0) {
    const annotate = process.env.GITHUB_ACTIONS === 'true';

    for (const message of errors) {
        console.error(annotate ? `::error::${message}` : `ERROR: ${message}`);
    }

    console.error(`\nVite build check failed for ${publicDir} (${errors.length} problem${errors.length === 1 ? '' : 's'}).`);
    process.exit(1);
}

for (const line of confirmed) {
    console.log(`OK  ${line}`);
}

console.log(`\nVite build is complete: ${publicDir}`);
