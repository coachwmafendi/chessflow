#!/usr/bin/env node
// Builds public/openings/openings.json (EPD -> [eco, name]) from data/openings/*.tsv (lichess-org/chess-openings, CC0).
const { Chess } = require('chess.js');
const fs = require('node:fs');
const path = require('node:path');

const src = path.join(__dirname, '..', 'data', 'openings');
const out = path.join(__dirname, '..', 'public', 'openings', 'openings.json');
const epd = (fen) => fen.split(' ').slice(0, 4).join(' ');

const map = {};
let rows = 0;
for (const f of ['a', 'b', 'c', 'd', 'e']) {
    const lines = fs.readFileSync(path.join(src, f + '.tsv'), 'utf8').trim().split('\n').slice(1);
    for (const line of lines) {
        const [eco, name, pgn] = line.split('\t');
        const g = new Chess();
        g.loadPgn(pgn);
        // Longer lines win when two names share a position (more specific).
        const key = epd(g.fen());
        if (!map[key] || map[key][2] < g.history().length) map[key] = [eco, name, g.history().length];
        rows++;
    }
}
const compact = Object.fromEntries(Object.entries(map).map(([k, [eco, name]]) => [k, [eco, name]]));
fs.mkdirSync(path.dirname(out), { recursive: true });
fs.writeFileSync(out, JSON.stringify(compact));
console.log(`${rows} lines -> ${Object.keys(compact).length} positions, ${(fs.statSync(out).size / 1024).toFixed(0)} KB`);
