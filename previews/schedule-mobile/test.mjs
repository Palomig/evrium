import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const html = readFileSync(join(here, 'index.html'), 'utf8');

const checks = [
  ['day mode', 'data-mode="day"'],
  ['week mode', 'data-mode="week"'],
  ['teacher selector', 'data-teacher="palomig"'],
  ['seven day controls', (html.match(/class="day-button/g) || []).length === 7],
  ['day timeline', 'id="dayView"'],
  ['week grid', 'id="weekView"'],
  ['lesson editor', 'id="lessonSheet"'],
  ['week settings', 'id="settingsSheet"'],
  ['bottom navigation', 'class="bottom-nav"'],
  ['temporary student', 'data-temporary="true"'],
  ['student cards', 'class="student-card"'],
  ['parent details', 'class="student-details"'],
  ['student editor', 'id="studentSheet"'],
  ['parent name field', 'id="parentName"'],
  ['parent phone field', 'id="parentPhone"'],
  ['no production requests', !/fetch\s*\(|XMLHttpRequest|\/zarplata\/api\//.test(html)],
];

for (const [name, condition] of checks) {
  const passed = typeof condition === 'string' ? html.includes(condition) : condition;
  if (!passed) throw new Error(`Missing contract: ${name}`);
}

console.log(`PASS ${checks.length} schedule prototype checks`);
