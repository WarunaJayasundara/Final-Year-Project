/**
 * Dev-only text contrast audit (WCAG AA), loaded only under `import.meta.env.DEV`.
 *
 * In the browser console:
 *   await __contrast.pages('dark', ['/dashboard', '/games'])   // one theme, several routes
 *   __contrast.scan()                                          // just the current page
 *
 * Use with `__preview.student()` / `__preview.admin()` (designPreview.ts) to check signed-in pages.
 * Every visible text node is compared with its effective background (translucent layers are
 * composited, ancestor opacity applied). Normal text needs 4.5:1, large text 3:1. Disabled
 * controls are skipped (WCAG exempts them). Only routes that have fixture data are meaningful.
 */

import { MotionGlobalConfig } from 'framer-motion';

// Audit runs must see every element in its final state, and a hidden browser pane never advances
// framer-motion's requestAnimationFrame fades, so content would stay transparent and go unchecked.
MotionGlobalConfig.skipAnimations = true;

interface Finding {
  ratio: number;
  need: number;
  text: string;
  tag: string;
  className: string;
}

type Rgba = [number, number, number, number];

const canvas = document.createElement('canvas');
canvas.width = canvas.height = 1;
const context = canvas.getContext('2d', { willReadFrequently: true });

/** Resolves any CSS color (including oklch and color-mix) to sRGB by painting it. */
function toRgba(color: string): Rgba {
  if (!context) return [0, 0, 0, 0];
  context.clearRect(0, 0, 1, 1);
  context.fillStyle = 'rgba(0,0,0,0)';
  context.fillStyle = color;
  context.fillRect(0, 0, 1, 1);
  const d = context.getImageData(0, 0, 1, 1).data;
  return [d[0], d[1], d[2], d[3] / 255];
}

function over(top: Rgba, bottom: Rgba): Rgba {
  const a = top[3];
  return [top[0] * a + bottom[0] * (1 - a), top[1] * a + bottom[1] * (1 - a), top[2] * a + bottom[2] * (1 - a), 1];
}

function luminance([r, g, b]: Rgba): number {
  const f = (v: number) => {
    const s = v / 255;
    return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
  };
  return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
}

function contrast(a: Rgba, b: Rgba): number {
  const x = luminance(a);
  const y = luminance(b);
  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
}

function scan(): Finding[] {
  const bodyBg = toRgba(getComputedStyle(document.body).backgroundColor);
  const base: Rgba = bodyBg[3] > 0 ? bodyBg : [255, 255, 255, 1];

  const backgroundOf = (element: Element): Rgba => {
    const layers: Rgba[] = [];
    for (let e: Element | null = element; e; e = e.parentElement) {
      const c = toRgba(getComputedStyle(e).backgroundColor);
      if (c[3] > 0) layers.push(c);
      if (c[3] >= 0.999) break;
    }
    return layers.reduceRight<Rgba>((bg, layer) => over(layer, bg), [...base] as Rgba);
  };

  const opacityOf = (element: Element): number => {
    let o = 1;
    for (let e: Element | null = element; e; e = e.parentElement) o *= parseFloat(getComputedStyle(e).opacity);
    return o;
  };

  const findings: Finding[] = [];
  const seen = new Set<string>();

  for (const el of document.querySelectorAll('body *')) {
    if (el.closest('.ambient, :disabled, [aria-disabled="true"]')) continue;
    const own = [...el.childNodes].filter((n) => n.nodeType === Node.TEXT_NODE && n.textContent?.trim());
    if (!own.length) continue;
    const rect = el.getBoundingClientRect();
    if (rect.width < 1 || rect.height < 1) continue;
    const style = getComputedStyle(el);
    if (style.visibility === 'hidden' || style.display === 'none' || style.webkitTextFillColor === 'rgba(0, 0, 0, 0)') continue;

    const opacity = opacityOf(el);
    if (opacity < 0.5) continue; // still fading in (a hidden pane never advances JS-driven fades)
    const raw = toRgba(el instanceof SVGElement ? style.fill : style.color);
    const bg = backgroundOf(el);
    const fg = over([raw[0], raw[1], raw[2], raw[3] * opacity], bg);
    const size = parseFloat(style.fontSize);
    const large = size >= 24 || (size >= 18.66 && parseInt(style.fontWeight, 10) >= 700);
    const need = large ? 3 : 4.5;
    const ratio = contrast(fg, bg);
    if (ratio >= need) continue;

    const text = own.map((n) => n.textContent?.trim()).join(' ').slice(0, 40);
    const key = `${text}${ratio.toFixed(1)}`;
    if (seen.has(key)) continue;
    seen.add(key);
    findings.push({ ratio: Number(ratio.toFixed(2)), need, text, tag: el.tagName.toLowerCase(), className: (el.getAttribute('class') ?? '').slice(0, 60) });
  }
  return findings;
}

/** Waits for one-shot animations (page enter, staggered fades) so half-faded text is not flagged. */
async function settle(): Promise<void> {
  for (let i = 0; i < 30; i++) {
    await new Promise((r) => setTimeout(r, 150));
    const running = document.getAnimations().some((a) => {
      const timing = a.effect?.getTiming();
      return a.playState === 'running' && timing && timing.iterations !== Infinity;
    });
    if (!running && i > 5) break;
  }
  // A hidden browser pane never advances animations; jump every one-shot animation to its end state.
  for (const a of document.getAnimations()) {
    const timing = a.effect?.getTiming();
    if (timing && timing.iterations !== Infinity) {
      try {
        a.finish();
      } catch {
        /* not finishable */
      }
    }
  }
  // Scroll-triggered reveals (whileInView) never fire off-screen in a hidden pane: show them as they would end up.
  for (const el of document.querySelectorAll<HTMLElement>('[style*="opacity"]')) {
    if (parseFloat(el.style.opacity) < 1) {
      el.style.opacity = '1';
      el.style.transform = 'none';
    }
  }
  await new Promise((r) => setTimeout(r, 200));
}

async function pages(theme: 'light' | 'dark', routes: string[]): Promise<Record<string, string[] | 'clean'>> {
  document.documentElement.classList.toggle('dark', theme === 'dark');
  const preview = (window as unknown as { __preview?: { go: (path: string) => void } }).__preview;
  const result: Record<string, string[] | 'clean'> = {};
  for (const route of routes) {
    preview?.go(route);
    await settle();
    const found = scan();
    result[`${theme} ${route}`] = found.length ? found.map((f) => `${f.ratio}/${f.need} "${f.text}" <${f.tag}> ${f.className}`) : 'clean';
  }
  return result;
}

/**
 * Non-text audit (WCAG 1.4.11, 3:1): outlines of form controls, stroked icons, and the label of
 * disabled controls (exempt from AA, but a label nobody can read still looks broken). Text is
 * covered by scan().
 */
function scanUi(): string[] {
  const bodyBg = toRgba(getComputedStyle(document.body).backgroundColor);
  const base: Rgba = bodyBg[3] > 0 ? bodyBg : [255, 255, 255, 1];
  const backgroundOf = (element: Element | null): Rgba => {
    const layers: Rgba[] = [];
    for (let e = element; e; e = e.parentElement) {
      const c = toRgba(getComputedStyle(e).backgroundColor);
      if (c[3] > 0) layers.push(c);
      if (c[3] >= 0.999) break;
    }
    return layers.reduceRight<Rgba>((bg, layer) => over(layer, bg), [...base] as Rgba);
  };
  const out: string[] = [];
  const seen = new Set<string>();
  const add = (key: string, line: string) => {
    if (seen.has(key)) return;
    seen.add(key);
    out.push(line);
  };

  const controls = 'input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea, [role=combobox], [role=checkbox], [role=switch]';
  for (const el of document.querySelectorAll(controls)) {
    const rect = el.getBoundingClientRect();
    if (rect.width < 4 || rect.height < 4) continue;
    const style = getComputedStyle(el);
    if (parseFloat(style.borderTopWidth) < 0.5) continue;
    const parentBg = backgroundOf(el.parentElement);
    const border = over(toRgba(style.borderTopColor), parentBg);
    const ratio = contrast(border, parentBg);
    if (ratio < 3) add(`b${el.tagName}${ratio.toFixed(1)}`, `outline ${ratio.toFixed(2)}/3 <${el.tagName.toLowerCase()}> ${(el.getAttribute('class') ?? '').slice(0, 50)}`);
  }

  for (const svg of document.querySelectorAll('svg.lucide, button svg, [role=button] svg')) {
    if (svg.closest('.ambient, .recharts-wrapper')) continue;
    const rect = svg.getBoundingClientRect();
    if (rect.width < 4) continue;
    const style = getComputedStyle(svg);
    if (style.stroke === 'none') continue;
    const bg = backgroundOf(svg.parentElement);
    const c = toRgba(style.stroke);
    const ratio = contrast(over([c[0], c[1], c[2], c[3] * parseFloat(style.opacity)], bg), bg);
    if (ratio < 3) add(`i${ratio.toFixed(1)}${svg.parentElement?.className}`, `icon ${ratio.toFixed(2)}/3 in <${svg.parentElement?.tagName.toLowerCase()}> ${(svg.parentElement?.getAttribute('class') ?? '').slice(0, 50)}`);
  }

  for (const el of document.querySelectorAll('button:disabled, [aria-disabled=true]')) {
    const rect = el.getBoundingClientRect();
    if (rect.width < 4 || !el.textContent?.trim()) continue;
    const style = getComputedStyle(el);
    let opacity = 1;
    for (let e: Element | null = el; e; e = e.parentElement) opacity *= parseFloat(getComputedStyle(e).opacity);
    if (opacity < 0.5) continue; // still fading in (a hidden pane never advances JS-driven fades)
    const bg = backgroundOf(el);
    const c = toRgba(style.color);
    const fg = over([c[0], c[1], c[2], c[3] * opacity], backgroundOf(el.parentElement));
    const face = over([bg[0], bg[1], bg[2], 1 * opacity], backgroundOf(el.parentElement));
    const ratio = contrast(fg, face);
    if (ratio < 3) add(`d${el.textContent.trim()}`, `disabled ${ratio.toFixed(2)}/3 "${el.textContent.trim().slice(0, 30)}"`);
  }
  return out;
}

async function pagesUi(theme: 'light' | 'dark', routes: string[]): Promise<Record<string, string[] | 'clean'>> {
  document.documentElement.classList.toggle('dark', theme === 'dark');
  const preview = (window as unknown as { __preview?: { go: (path: string) => void } }).__preview;
  const result: Record<string, string[] | 'clean'> = {};
  for (const route of routes) {
    preview?.go(route);
    await settle();
    const found = scanUi();
    result[`${theme} ${route}`] = found.length ? found : 'clean';
  }
  return result;
}

(window as unknown as { __contrast: unknown }).__contrast = { scan, pages, scanUi, pagesUi };
