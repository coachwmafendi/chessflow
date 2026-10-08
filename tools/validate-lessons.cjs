// Validates data/lessons.json against chess rules (chess.js v1).
// Usage: node tools/validate-lessons.cjs [data/lessons.json]
const fs = require('fs');
const { Chess, validateFen } = require('chess.js');
const file = process.argv[2] || 'data/lessons.json';
const { lessons } = JSON.parse(fs.readFileSync(file, 'utf8'));
const uci = (u) => ({ from: u.slice(0, 2), to: u.slice(2, 4), promotion: u[4] });
const tryMove = (g, m) => { try { return g.move(m); } catch { return null; } };
const isMate = (g, m) => { const r = tryMove(g, m); const v = r && g.isCheckmate(); if (r) g.undo(); return v; };
let errs = 0; const E = (l, i, m) => { errs++; console.log('ERR', l.id, 'step', i, m); };
for (const l of lessons) l.steps.forEach((st, i) => {
  let g = null;
  if (st.fen) {
    const v = validateFen(st.fen);
    if (!v.ok) { E(l, i, 'bad fen: ' + v.error); return; }
    g = new Chess(st.fen);
    const f = st.fen.split(' '); f[1] = f[1] === 'w' ? 'b' : 'w'; f[3] = '-';
    try { if (new Chess(f.join(' ')).inCheck()) E(l, i, 'side not to move is in check'); } catch {}
  }
  if (st.demo) { const h = new Chess(st.fen); for (const u of st.demo) if (!tryMove(h, uci(u))) { E(l, i, 'demo illegal ' + u); break; } }
  if (st.type === 'puzzle') {
    const ms = g.moves({ verbose: true });
    if (st.line) { const h = new Chess(st.fen); st.line.forEach((u) => { if (!tryMove(h, uci(u))) E(l, i, 'line illegal ' + u); }); }
    if (st.acceptSan) st.acceptSan.forEach((s) => { if (!ms.find((m) => m.san === s)) E(l, i, 'acceptSan not legal: ' + s); });
    if (st.acceptMate && !ms.some((m) => isMate(g, m))) E(l, i, 'acceptMate but no mate in 1');
    if (st.accept) {
      const a = st.accept;
      const pass = ms.filter((m) => {
        if (a.piece && m.piece !== a.piece) return false; if (a.notPiece && m.piece === a.notPiece) return false;
        if (a.to && !a.to.includes(m.to)) return false; if (a.toFile && !a.toFile.includes(m.to[0])) return false;
        if (a.capture && !m.captured) return false;
        if (a.noMateInOne) { g.move(m); const bad = g.moves({ verbose: true }).some((r) => isMate(g, r)); g.undo(); if (bad) return false; }
        return true;
      });
      if (!pass.length) E(l, i, 'no legal move satisfies accept');
    }
  }
  if (st.type === 'quiz' && !st.options.some((o) => o.ok)) E(l, i, 'quiz has no correct option');
  if (st.type === 'tap') st.seq.forEach((it) => it.sq.forEach((s) => { if (!/^[a-h][1-8]$/.test(s)) E(l, i, 'bad square ' + s); }));
});
console.log(`${lessons.length} lessons, ${lessons.reduce((a, l) => a + l.steps.length, 0)} steps, ${errs} errors`);
process.exit(errs ? 1 : 0);
