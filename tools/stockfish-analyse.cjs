// Minimal Stockfish wrapper: analyse(fen, depth, multipv) -> [{move, cp, mate}]
const { spawn } = require('child_process');
// Requires: npm i -D stockfish@16.0.0   Usage: node tools/stockfish-analyse.cjs "<FEN>" [depth] [multipv]
const path = require('path');
function analyse(fen, depth = 18, multipv = 10) {
  return new Promise((resolve) => {
    const p = spawn('node', [require.resolve('stockfish/src/stockfish-nnue-16-no-simd.js')]);
    let buf = '', lines = {}, started = false;
    const send = (s) => p.stdin.write(s + '\n');
    p.stdout.on('data', (d) => {
      buf += d.toString();
      let idx;
      while ((idx = buf.indexOf('\n')) >= 0) {
        const line = buf.slice(0, idx).trim(); buf = buf.slice(idx + 1);
        if (line === 'uciok') { send('setoption name MultiPV value ' + multipv); send('isready'); }
        else if (line === 'readyok' && !started) { started = true; send('position fen ' + fen); send('go depth ' + depth); }
        else if (line.startsWith('info') && line.includes(' pv ') && line.includes(' depth ' + depth + ' ')) {
          const mp = +line.match(/multipv (\d+)/)[1];
          const mv = line.match(/ pv (\S+)/)[1];
          const cp = line.match(/score cp (-?\d+)/), mt = line.match(/score mate (-?\d+)/);
          lines[mp] = { move: mv, cp: cp ? +cp[1] : null, mate: mt ? +mt[1] : null };
        } else if (line.startsWith('bestmove')) {
          p.kill(); resolve(Object.keys(lines).sort((a, b) => a - b).map((k) => lines[k]));
        }
      }
    });
    setTimeout(() => send('uci'), 1500);
  });
}
module.exports = { analyse };
if (require.main === module) {
  analyse(process.argv[2], +(process.argv[3] || 18), +(process.argv[4] || 8)).then((r) => console.log(JSON.stringify(r)));
}
