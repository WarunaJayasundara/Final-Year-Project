// Sinhala/English locale consistency check.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'src', 'locales');
const flat = (o, p = '') => Object.entries(o).flatMap(([k, v]) => (v && typeof v === 'object' ? flat(v, p + k + '.') : [[p + k, String(v)]]));

const ALLOWED = /^[඀-෿‌‍ -~ ±·×÷–—‘’“”…→≥≤°•©θ\n]$/u;
const ORPHAN = /(^|[ \t0-9.,;:!?()/"'-])[්-ෟෲෳ]/;
const COLLOQUIAL = /(^|\s)(කළා|කරනවා|වෙනවා|යනවා|මේ)(\s|$|[.,!?])/;
// discouraged -> preferred, per the style guide
const DISCOURAGED = [
  ['අනාවැකි', 'පුරෝකථනය (prediction)'],
  ['මාදිලි විභාග', 'ආදර්ශ විභාගය (mock exam)'],
  ['නායකත්ව පුවරු', 'ශ්‍රේණි පුවරුව (leaderboard)'],
  ['ස්ථානගත කිරීම', 'මට්ටම් නිර්ණය (placement)'],
  ['බැඩ්ජ්', 'පදක්කම (badge)'],
  ['ප්‍රදේශ', 'අංශ (areas of skill)'],
  ['චෙක්-ඉන්', 'දෛනික සටහන (check-in)'],
];
// Values that legitimately contain no Sinhala letters.
const NO_SINHALA_OK = new Set(['appName', 'feedback.dimensions.ui']);

const errors = [];
const warnings = [];
const files = fs.readdirSync(path.join(dir, 'en'));
let keys = 0;

for (const f of files) {
  const en = Object.fromEntries(flat(JSON.parse(fs.readFileSync(path.join(dir, 'en', f), 'utf8'))));
  const siPath = path.join(dir, 'si', f);
  if (!fs.existsSync(siPath)) { errors.push(`${f}: Sinhala file missing`); continue; }
  const si = Object.fromEntries(flat(JSON.parse(fs.readFileSync(siPath, 'utf8'))));

  for (const k of Object.keys(en)) if (!(k in si)) errors.push(`${f} ${k}: missing in Sinhala`);
  for (const k of Object.keys(si)) if (!(k in en)) errors.push(`${f} ${k}: only in Sinhala`);

  for (const [k, v] of Object.entries(si)) {
    if (!(k in en)) continue;
    keys++;
    const where = `${f} ${k}`;
    const ph = (s) => (s.match(/\{\{[^}]+\}\}/g) || []).sort().join('|');
    if (ph(en[k]) !== ph(v)) errors.push(`${where}: placeholders differ (${ph(en[k])} vs ${ph(v)})`);
    const bad = [...v].filter((c) => !ALLOWED.test(c));
    if (bad.length) errors.push(`${where}: foreign characters ${bad.join(' ')}`);
    if (ORPHAN.test(v)) errors.push(`${where}: orphaned vowel sign`);
    if (COLLOQUIAL.test(v)) errors.push(`${where}: colloquial verb ending`);
    for (const [term, use] of DISCOURAGED) if (v.includes(term)) errors.push(`${where}: uses "${term}", prefer ${use}`);
    if (!/[඀-෿]/.test(v) && !NO_SINHALA_OK.has(k) && /[A-Za-z]{3,}/.test(v.replace(/\{\{[^}]+\}\}/g, ''))) warnings.push(`${where}: no Sinhala letters`);
    const digits = (s) => (s.replace(/\{\{[^}]+\}\}/g, '').match(/\d+/g) || []).sort().join(',');
    // English may say "1 session" where Sinhala says "a session"; only digits that Sinhala adds or changes are suspicious.
    const enDigits = new Set(digits(en[k]).split(',').filter(Boolean));
    if (digits(v).split(',').filter(Boolean).some((d) => !enDigits.has(d))) warnings.push(`${where}: digits differ`);
  }
}

warnings.forEach((w) => console.warn('warn  ' + w));
errors.forEach((e) => console.error('ERROR ' + e));
console.log(`\nchecked ${keys} Sinhala strings in ${files.length} files: ${errors.length} error(s), ${warnings.length} warning(s)`);
process.exit(errors.length ? 1 : 0);
