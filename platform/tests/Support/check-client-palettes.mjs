import fs from 'node:fs';
import assert from 'node:assert/strict';
import '../../public/client-palette.js';
const fixtures = JSON.parse(fs.readFileSync(new URL('../Fixtures/client-palette-v1.json', import.meta.url), 'utf8'));
for (const [source, expected] of Object.entries(fixtures)) assert.deepEqual(globalThis.ClientPalette.generate(source), expected, source);
console.log(Object.keys(fixtures).length + ' browser/server palette parity fixtures passed.');
