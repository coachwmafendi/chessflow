(function(){
const FILES='abcdefgh';
const nm=i=>FILES[i%8]+((i>>3)+1);
const S=n=>FILES.indexOf(n[0])+(parseInt(n[1],10)-1)*8;
const KN=[[1,2],[2,1],[2,-1],[1,-2],[-1,-2],[-2,-1],[-2,1],[-1,2]];
const DIAG=[[1,1],[1,-1],[-1,1],[-1,-1]],ORTH=[[1,0],[-1,0],[0,1],[0,-1]];
const VAL={p:1,n:3,b:3,r:5,q:9,k:0};
const PNAME={K:'Raja',Q:'Menteri',R:'Tir',B:'Gajah',N:'Kuda',P:'Bidak'};
const $=s=>document.querySelector(s);
const HAS_CHESS=typeof Chess!=='undefined';

/* ---------- helpers ---------- */
const pcs=str=>(str||'').trim().split(/\s+/).filter(Boolean).map(t=>({c:t[0],t:t[1],sq:S(t.slice(2))}));
function fenList(fen){const out=[];const rows=fen.split(' ')[0].split('/');rows.forEach((row,ri)=>{let f=0;for(const ch of row){if(/\d/.test(ch)){f+=+ch;continue}out.push({c:ch===ch.toUpperCase()?'w':'b',t:ch.toUpperCase(),sq:(7-ri)*8+f});f++}});return out}
function expandSq(list){const out=[];(list||[]).forEach(x=>{if(typeof x!=='string'){out.push(x);return}
  if(x.startsWith('file:')){const f=FILES.indexOf(x[5]);for(let r=0;r<8;r++)out.push(r*8+f)}
  else if(x.startsWith('rank:')){const r=+x[5]-1;for(let f=0;f<8;f++)out.push(r*8+f)}
  else out.push(S(x))});return out}
function gen(list,sq){const occ=new Map(list.map(p=>[p.sq,p]));const p=occ.get(sq);if(!p)return[];const f=sq%8,r=sq>>3,out=[];
  const add=(x,y)=>{if(x<0||x>7||y<0||y>7)return false;const i=y*8+x,o=occ.get(i);if(o){if(o.c!==p.c)out.push(i);return false}out.push(i);return true};
  const slide=d=>d.forEach(([dx,dy])=>{let x=f+dx,y=r+dy;while(add(x,y)){x+=dx;y+=dy}});
  switch(p.t){case'N':KN.forEach(([a,b])=>add(f+a,r+b));break;case'B':slide(DIAG);break;case'R':slide(ORTH);break;case'Q':slide(DIAG.concat(ORTH));break;
   case'K':DIAG.concat(ORTH).forEach(([a,b])=>add(f+a,r+b));break;
   case'P':{const d=p.c==='w'?1:-1,st=p.c==='w'?1:6,y=r+d;if(y>=0&&y<8&&!occ.has(y*8+f)){out.push(y*8+f);if(r===st&&!occ.has((r+2*d)*8+f))out.push((r+2*d)*8+f)}
     [-1,1].forEach(dx=>{const x=f+dx;if(x>=0&&x<8&&y>=0&&y<8){const o=occ.get(y*8+x);if(o&&o.c!==p.c)out.push(y*8+x)}})}}
  return out}
function lpath(a,b){const fa=a%8,ra=a>>3,df=b%8-fa,dr=(b>>3)-ra;const mid=Math.abs(dr)===2?(ra+dr)*8+fa:ra*8+fa+df;return[a,mid,b]}
function pathFor(t,a,b){return t==='N'?{path:lpath(a,b),kind:'path'}:{from:a,to:b,kind:'path'}}
function tourOpt(list,start,stars){const piece=list.find(p=>p.sq===start);const others=list.filter(p=>p!==piece);
  const dist=(a,b)=>{if(a===b)return 0;const d=new Array(64).fill(-1);d[a]=0;const q=[a];while(q.length){const x=q.shift();for(const y of gen(others.concat([{c:piece.c,t:piece.t,sq:x}]),x))if(d[y]<0){d[y]=d[x]+1;if(y===b)return d[y];q.push(y)}}return 99};
  let best=1e9;const perm=(cur,rest,acc)=>{if(acc>=best)return;if(!rest.length){best=acc;return}rest.forEach((s,i)=>perm(s,rest.filter((_,j)=>j!==i),acc+dist(cur,s)))};perm(start,stars,0);return best}
const uci=u=>({from:u.slice(0,2),to:u.slice(2,4),promotion:u[4]});
function oppHasMate(g){const ms=g.moves({verbose:true});for(const m of ms){g.move(m);const mate=g.in_checkmate();g.undo();if(mate)return true}return false}

/* ---------- storage ---------- */
const KEY='chessflow-v2';
let P={xp:0,lessons:{},guru:false};
try{const s=localStorage.getItem(KEY);if(s)P=Object.assign(P,JSON.parse(s))}catch(e){}
const save=()=>{try{localStorage.setItem(KEY,JSON.stringify(P))}catch(e){}};
const totalStars=()=>Object.values(P.lessons).reduce((a,l)=>a+(l.stars||0),0);
function refreshStats(){$('#st-stars').textContent=totalStars();$('#st-xp').textContent=P.xp}

/* ---------- sound ---------- */
let soundOn=true,ac=null;
try{if(localStorage.getItem(KEY+'-snd')==='0')soundOn=false}catch(e){}
function setSndLabel(){const b=$('#snd');b.textContent='Bunyi: '+(soundOn?'Hidup':'Tutup');b.setAttribute('aria-pressed',String(soundOn))}
function tone(seq){if(!soundOn)return;try{ac=ac||new(window.AudioContext||window.webkitAudioContext)();let t=ac.currentTime;for(const[f,d]of seq){const o=ac.createOscillator(),g=ac.createGain();o.type='triangle';o.frequency.value=f;g.gain.setValueAtTime(.0001,t);g.gain.exponentialRampToValueAtTime(.16,t+.02);g.gain.exponentialRampToValueAtTime(.0001,t+d);o.connect(g).connect(ac.destination);o.start(t);o.stop(t+d+.02);t+=d*.8}}catch(e){}}
const SFX={good:()=>tone([[660,.12],[880,.18]]),bad:()=>tone([[220,.22]]),star:()=>tone([[988,.1],[1318,.16]]),win:()=>tone([[523,.12],[659,.12],[784,.12],[1046,.3]]),move:()=>tone([[420,.06]])};

/* ---------- board ---------- */
function Board(root,orient){
  const o=orient||'w';root.innerHTML='';
  const wrap=document.createElement('div');wrap.className='board';
  const grid=document.createElement('div');grid.className='grid';grid.setAttribute('aria-label','Papan catur');
  const pos=i=>{const f=i%8,r=i>>3;return o==='w'?[f,7-r]:[7-f,r]};
  const els=[];
  for(let row=0;row<8;row++)for(let col=0;col<8;col++){
    const i=o==='w'?(7-row)*8+col:row*8+(7-col),f=i%8,r=i>>3,b=document.createElement('button');
    b.type='button';b.className='sq '+((f+r)%2?'light':'dark');b.dataset.sq=i;b.setAttribute('aria-label','Petak '+nm(i));
    if(col===0)b.insertAdjacentHTML('beforeend','<span class="cr">'+(r+1)+'</span>');
    if(row===7)b.insertAdjacentHTML('beforeend','<span class="cf">'+FILES[f]+'</span>');
    grid.appendChild(b);els[i]=b}
  const NS='http://www.w3.org/2000/svg',svg=document.createElementNS(NS,'svg');svg.setAttribute('class','arrows');svg.setAttribute('viewBox','0 0 8 8');svg.setAttribute('aria-hidden','true');
  svg.innerHTML='<defs><marker id="m-coral" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="3.2" markerHeight="3.2" orient="auto-start-reverse"><path class="m-coral" d="M0 0L10 5L0 10z"/></marker><marker id="m-path" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="3.2" markerHeight="3.2" orient="auto-start-reverse"><path class="m-path" d="M0 0L10 5L0 10z"/></marker></defs><g></g>';
  const g=svg.querySelector('g');const layer=document.createElement('div');layer.className='pieces';
  wrap.append(grid,svg,layer);root.appendChild(wrap);
  const pieces=new Map();let uid=0,handler=null,M={};
  grid.addEventListener('click',e=>{const b=e.target.closest('.sq');if(b&&handler)handler(+b.dataset.sq)});
  const place=(el,i)=>{const[x,y]=pos(i);el.style.transform='translate('+x*100+'%,'+y*100+'%)'};
  const ctr=i=>{const[x,y]=pos(i);return[x+.5,y+.5]};
  const api={orient:o,
    set(list){layer.innerHTML='';pieces.clear();list.forEach(p=>api.add(p));M={};api.mark({});api.arrows([])},
    setFen(fen){api.set(fenList(fen))},
    quiet(fen){const want=fenList(fen),key=p=>p.c+p.t+p.sq;const have=new Set(api.all().map(key));
      if(want.length===have.size&&want.every(p=>have.has(key(p))))return;layer.innerHTML='';pieces.clear();want.forEach(p=>api.add(p))},
    add(p){const id=++uid,el=document.createElement('div');el.className='piece pc '+p.c+p.t;el.style.transition='none';place(el,p.sq);layer.appendChild(el);void el.offsetWidth;el.style.transition='';pieces.set(id,{id,t:p.t,c:p.c,sq:p.sq,el});return id},
    at(i){for(const p of pieces.values())if(p.sq===i)return p;return null},
    all(){return[...pieces.values()]},
    remove(i){const p=api.at(i);if(!p)return;pieces.delete(p.id);p.el.classList.add('captured');setTimeout(()=>p.el.remove(),350)},
    move(from,to){const p=api.at(from);if(!p)return;const cap=api.at(to);if(cap&&cap!==p)api.remove(to);
      p.sq=to;p.el.classList.remove('hop');void p.el.offsetWidth;if(p.t==='N')p.el.classList.add('hop');place(p.el,to)},
    applyMove(m){const from=S(m.from),to=S(m.to);
      if(m.flags.includes('e'))api.remove((from>>3)*8+to%8);
      api.move(from,to);
      if(m.flags.includes('k')){const r=from>>3;api.move(r*8+7,r*8+5)}
      if(m.flags.includes('q')){const r=from>>3;api.move(r*8,r*8+3)}
      if(m.promotion){const p=api.at(to);if(p){p.t=m.promotion.toUpperCase();setTimeout(()=>{p.el.className='piece pc '+p.c+p.t},260)}}},
    mark(patch){M=Object.assign({},M,patch);
      const set=k=>new Set(expandSq(M[k]));const dots=set('dots'),good=set('good'),ring=set('ring'),star=set('stars'),hl=set('hl');
      const sel=M.sel==null?-1:(typeof M.sel==='string'?S(M.sel):M.sel);
      els.forEach((b,i)=>{b.classList.toggle('dot',dots.has(i));b.classList.toggle('good',good.has(i));b.classList.toggle('ring',ring.has(i));b.classList.toggle('star',star.has(i));b.classList.toggle('hl',hl.has(i));b.classList.toggle('sel',sel===i)})},
    flash(i){const b=els[i];b.classList.remove('bad');void b.offsetWidth;b.classList.add('bad')},
    arrows(list){g.innerHTML='';(list||[]).forEach(a=>{
      if(Array.isArray(a))a={from:S(a[0]),to:S(a[1])};
      const kind=a.kind||'coral';let el;
      if(a.path){el=document.createElementNS(NS,'polyline');el.setAttribute('points',a.path.map(ctr).map(p=>p.join(',')).join(' '))}
      else{const[x1,y1]=ctr(a.from),[x2,y2]=ctr(a.to),dx=x2-x1,dy=y2-y1,L=Math.hypot(dx,dy),k=(L-.38)/L;
        el=document.createElementNS(NS,'line');el.setAttribute('x1',x1);el.setAttribute('y1',y1);el.setAttribute('x2',x1+dx*k);el.setAttribute('y2',y1+dy*k)}
      el.setAttribute('class','a-'+kind+' draw');el.setAttribute('stroke-width','.16');el.setAttribute('pathLength','20');el.setAttribute('marker-end','url(#m-'+kind+')');g.appendChild(el)})},
    on(fn){handler=fn}};
  return api}

/* ---------- step types ---------- */
function base(st,c){
  const b=c.newBoard(st.orient);
  if(st.fen)b.setFen(st.fen);else b.set(pcs(st.pcs));
  b.mark({hl:st.hl||[],ring:st.ring||[],dots:st.dots||[]});
  if(st.arrows)b.arrows(st.arrows);
  if(st.counter)c.counter(st.counter);
  return b}

const TYPES={
 explain(st,c){const b=base(st,c);
  if(st.showMoves){const list=pcs(st.pcs),k=S(st.showMoves),t=gen(list,k),pt=list.find(p=>p.sq===k).t;
    b.mark({sel:k,dots:t});if(st.initPath)b.arrows([pathFor(pt,k,S(st.initPath))]);
    b.on(i=>{if(!t.includes(i))return;b.arrows([pathFor(pt,k,i)]);SFX.move();c.status(PNAME[pt]+' boleh pergi dari '+nm(k)+' ke '+nm(i)+'.','info')})}
  if(st.demo&&HAS_CHESS){const play=()=>{c.clear();base(st,c);c.status('','');const g=new Chess(st.fen);let t=800;
      st.demo.forEach((u,idx)=>{c.later(()=>{const bb=c.board();bb.arrows([]);bb.mark({ring:[]});const m=g.move(uci(u));if(!m)return;bb.applyMove(m);SFX.move();
        c.later(()=>bb.quiet(g.fen()),420);
        const n=st.demoNotes&&st.demoNotes[idx];if(n){if(n.arrows)bb.arrows(n.arrows);if(n.ring)bb.mark({ring:n.ring});if(n.status)c.status(n.status,'info')}
        if(idx===st.demo.length-1&&st.demoEnd)c.status(st.demoEnd,'good')},t);t+=(st.demoNotes&&st.demoNotes[idx])?1700:950});};
    c.actions([{label:'Tengok semula',fn:play}]);play()}
  c.done()},

 find(st,c){const b=base(st,c),list=pcs(st.pcs),k=S(st.piece),t=gen(list,k),pt=list.find(p=>p.sq===k).t,found=new Set();
  b.mark({sel:k});c.counter('Jumpa: 0/'+t.length);
  b.on(i=>{if(i===k||found.has(i))return;
   if(t.includes(i)){found.add(i);b.mark({good:[...found]});b.arrows([pathFor(pt,k,i)]);SFX.good();c.counter('Jumpa: '+found.size+'/'+t.length);
     if(found.size===t.length){c.status(st.done||'Hebat! Awak jumpa semua petak.','good');SFX.win();c.done()}else c.status('Betul! Teruskan cari.','good')}
   else{b.flash(i);SFX.bad();c.mistake();const o=b.at(i);
     c.status(o&&o.c===list.find(p=>p.sq===k).c?'Tak boleh. Itu buah sendiri.':nm(i)+' bukan petak yang '+PNAME[pt]+' boleh pergi.','bad')}})},

 collect(st,c){const list0=pcs(st.pcs),start=S(st.piece),STARS=st.stars.map(S),opt=tourOpt(list0,start,STARS);let k,left,moves,fin,b;
  const draw=()=>{const list=b.all().map(p=>({c:p.c,t:p.t,sq:p.sq}));b.mark({sel:k,dots:fin?[]:gen(list,k),stars:[...left]});c.counter('Langkah: '+moves+' · Bintang: '+(STARS.length-left.size)+'/'+STARS.length)};
  const reset=()=>{b=base(st,c);k=start;left=new Set(STARS);moves=0;fin=false;draw();c.status('','');
   b.on(i=>{if(fin)return;const list=b.all().map(p=>({c:p.c,t:p.t,sq:p.sq}));if(!gen(list,k).includes(i))return;b.move(k,i);k=i;moves++;
    if(left.has(i)){left.delete(i);SFX.star()}else SFX.move();
    if(!left.size){fin=true;const extra=moves-opt;if(extra>3)c.mistake(2);else if(extra>0)c.mistake(1);
      c.status('Misi selesai dalam '+moves+' langkah! '+(extra<=0?'Itu jumlah paling sedikit. Hebat!':'Paling sedikit ialah '+opt+' langkah. Tekan "Mula semula" kalau nak cuba lagi.'),'good');SFX.win();c.done()}
    draw()})};
  c.actions([{label:'Mula semula',fn:reset}]);reset()},

 tap(st,c){const b=base(st,c);let idx=0,got=new Set(),busy=false;
  const show=()=>{const it=st.seq[idx];c.task(it.ask);c.counter(st.seq.length>1?'Soalan '+(idx+1)+'/'+st.seq.length:'')};show();
  b.on(i=>{if(busy)return;const it=st.seq[idx],ok=it.sq.map(S);
   if(ok.includes(i)){if(got.has(i))return;got.add(i);b.mark({good:[...got]});SFX.good();
     if(it.all&&got.size<ok.length){c.status('Betul! Ada lagi.','good');return}
     if(it.arrows)b.arrows(it.arrows);
     if(it.dotsOf)b.mark({dots:it.dotsOf.flatMap(s=>gen(b.all().map(p=>({c:p.c,t:p.t,sq:p.sq})),S(s)))});
     c.status(it.ok||'Betul!','good');
     if(idx<st.seq.length-1){busy=true;c.later(()=>{idx++;got=new Set();b.mark({good:[]});b.arrows(st.arrows||[]);busy=false;show()},1100)}
     else{SFX.win();c.done()}}
   else{b.flash(i);SFX.bad();c.mistake();const msg=(st.wrongMap&&st.wrongMap[nm(i)])||it.wrong||st.wrong||'Bukan itu. Cuba lagi.';c.status(msg.replace('{sq}',nm(i)),'bad')}})},

 quiz(st,c){base(st,c);const box=c.opts();
  st.options.forEach(op=>{const bt=document.createElement('button');bt.type='button';bt.className='opt';bt.innerHTML=op.t;
   bt.onclick=()=>{if(op.ok){bt.classList.add('good');box.querySelectorAll('button').forEach(x=>x.disabled=true);c.status(op.why||'Betul!','good');c.statusHtml(op.why||'Betul!');SFX.good();c.done()}
     else{bt.classList.add('bad');bt.disabled=true;c.mistake();SFX.bad();c.status('','bad');c.statusHtml(op.why||'Cuba lagi.')}};
   box.appendChild(bt)})},

 puzzle(st,c){if(!HAS_CHESS)return c.status('Enjin catur gagal dimuatkan. Semak sambungan internet.','bad');
  const b=base(st,c),g=new Chess(st.fen),user=g.turn();let ply=0,sel=null,busy=false,wrong=0;
  const hint=()=>{if(st.hintRing)b.mark({ring:st.hintRing})};
  if(st.hintRing)c.actions([{label:'Petunjuk',fn:()=>{c.mistake();hint();c.status('Petunjuk: perhatikan petak bulat merah.','info')}}]);
  const accepts=res=>{
   if(st.line){const exp=st.line[ply];if(res.from+res.to===exp.slice(0,4))return true;if(st.alts&&st.alts[ply]&&st.alts[ply].includes(res.san))return true;return ply===0&&st.acceptSan&&st.acceptSan.includes(res.san)}
   if(st.acceptSan)return st.acceptSan.includes(res.san);
   if(st.acceptMate)return g.in_checkmate();
   const a=st.accept||{};
   if(a.piece&&res.piece!==a.piece)return false;if(a.notPiece&&res.piece===a.notPiece)return false;
   if(a.to&&!a.to.includes(res.to))return false;if(a.toFile&&!a.toFile.includes(res.to[0]))return false;
   if(a.capture&&!res.captured)return false;if(a.noMateInOne&&oppHasMate(g))return false;return true};
  b.on(i=>{if(busy)return;const sq=nm(i),p=g.get(sq);
   if(p&&p.color===user&&g.turn()===user){sel=sq;b.mark({sel:i,dots:g.moves({square:sq,verbose:true}).map(m=>S(m.to))});return}
   if(!sel)return;const legal=g.moves({square:sel,verbose:true}).filter(m=>m.to===sq);if(!legal.length)return;
   let pr;if(legal[0].promotion){const exp=st.line&&st.line[ply];pr=exp&&exp.length===5?exp[4]:'q'}
   const res=g.move({from:legal[0].from,to:sq,promotion:pr});b.applyMove(res);b.mark({sel:null,dots:[],ring:[]});b.arrows([]);sel=null;
   c.later(()=>b.quiet(g.fen()),420);
   if(accepts(res)){ply++;SFX.good();b.mark({good:[S(res.to)]});
     if(st.line&&ply<st.line.length){
       if(ply===1&&st.after1){if(st.after1.arrows)b.arrows(st.after1.arrows);if(st.after1.status)c.status(st.after1.status,'good')}else c.status('Bagus!','good');
       busy=true;c.later(()=>{const m=g.move(uci(st.line[ply]));b.arrows([]);b.mark({good:[]});b.applyMove(m);SFX.move();ply++;c.later(()=>b.quiet(g.fen()),420);
         if(ply>=st.line.length){c.status(st.win||'Betul!','good');SFX.win();c.done();return}
         busy=false;const mi=(ply-2)/2;c.status((st.mids&&st.mids[mi])||st.mid||'Teruskan!','info')},st.after1&&ply===1?1700:900);return}
     busy=true;c.status(st.win||'Betul!','good');SFX.win();c.done()}
   else{wrong++;c.mistake();SFX.bad();b.flash(i);
     c.status(g.in_stalemate()?'Stalemate! Itu seri, bukan menang. Raja lawan mesti ada langkah atau kena sah mati.':(st.wrongMsg||'Belum betul. Cuba lagi.'),'bad');
     busy=true;c.later(()=>{g.undo();b.quiet(g.fen());b.mark({good:[]});busy=false;if(wrong>=2)hint()},950)}})},

 play(st,c){if(!HAS_CHESS)return c.status('Enjin catur gagal dimuatkan. Semak sambungan internet.','bad');
  let g,b,user,n,over,sel;
  const cnt=()=>c.counter('Langkah: '+n+'/'+st.maxMoves);
  const fail=msg=>{over=true;c.mistake();SFX.bad();c.status(msg+' Tekan "Cuba lagi".','bad')};
  const reset=()=>{b=base(st,c);g=new Chess(st.fen);user=g.turn();n=0;over=false;sel=null;cnt();c.status('','');b.on(onTap)};
  const userPawns=()=>g.board().flat().filter(x=>x&&x.color===user&&x.type==='p').length;
  function onTap(i){if(over||g.turn()!==user)return;const sq=nm(i),p=g.get(sq);
   if(p&&p.color===user){sel=sq;b.mark({sel:i,dots:g.moves({square:sq,verbose:true}).map(m=>S(m.to))});return}
   if(!sel)return;const legal=g.moves({square:sel,verbose:true}).filter(m=>m.to===sq);if(!legal.length)return;
   const res=g.move({from:sel,to:sq,promotion:legal[0].promotion?'q':undefined});b.applyMove(res);b.mark({sel:null,dots:[]});sel=null;n++;cnt();SFX.move();
   c.later(()=>b.quiet(g.fen()),420);
   const won=(st.goal==='mate'&&g.in_checkmate())||(st.goal==='promote'&&res.flags.includes('p'))||(st.goal==='captureQ'&&res.captured==='q');
   if(won){over=true;b.mark({good:[S(res.to)]});c.status(st.win,'good');SFX.win();c.done();return}
   if(g.in_stalemate())return fail('Stalemate! Raja hitam tiada langkah tapi tidak kena sah. Itu seri.');
   if(g.in_draw())return fail('Permainan seri.');
   if(n>=st.maxMoves)return fail('Sudah '+st.maxMoves+' langkah.');
   c.later(()=>{const m=bot(g,st.bot,user);if(!m)return;const r=g.move(m);b.applyMove(r);c.later(()=>b.quiet(g.fen()),420);
     if(st.goal==='promote'&&!userPawns())return fail('Raja hitam tangkap bidak awak!');
     if(st.goal==='mate'&&r.captured&&r.captured!=='p')return fail('Alamak, '+PNAME[r.captured.toUpperCase()]+' awak dimakan! Jauhkan buah daripada Raja lawan.');
     if(st.goal==='captureQ'&&n>=st.maxMoves)return fail('Menteri hitam terlepas.');
     c.status(g.in_check()?'Sah!':'','info')},550)}
  c.actions([{label:'Cuba lagi',fn:reset}]);reset()}
};

/* ---------- simple bot ---------- */
function bot(g,kind,user,noise){const me=g.turn(),ms=g.moves({verbose:true});if(!ms.length)return null;let best=null,bs=-1e9;
  for(const m of ms){g.move(m);let s=m.captured?VAL[m.captured]*100:0;
   const rep=g.moves({verbose:true});let threat=0;for(const r of rep){if(r.captured)threat=Math.max(threat,VAL[r.captured]||0)}
   if(g.in_checkmate())s-=10000;
   const ksq=g.board().flat().find(x=>x&&x.type==='k'&&x.color===me);
   let kpos=null;g.board().forEach((row,ri)=>row.forEach((x,fi)=>{if(x&&x.type==='k'&&x.color===me)kpos=[fi,7-ri]}));
   if(kind==='flee'||kind==='defend'){s-=threat*60;if(kpos)s-=Math.max(Math.abs(kpos[0]-3.5),Math.abs(kpos[1]-3.5))*6;
     const f=g.fen().split(' ');f[1]=me;f[3]='-';try{const t=new Chess(f.join(' '));s+=t.moves().length*3}catch(e){}}
   if(kind==='chase'&&kpos){let d=99;g.board().forEach((row,ri)=>row.forEach((x,fi)=>{if(x&&x.type==='p'&&x.color===user){const pr=user==='w'?7:0;d=Math.min(d,Math.max(Math.abs(kpos[0]-fi),Math.abs(kpos[1]-pr)))}}));s-=d*10}
   g.undo();s+=Math.random()*(noise||1);if(s>bs){bs=s;best=m}}
  return{from:best.from,to:best.to,promotion:best.promotion}}

/* ---------- data ---------- */
const LES=LESSONS_DATA;LES.forEach((l,i)=>{l.idx=i;l.xp=l.xp||(l.exam?80:l.steps.length*10)});
const BYID=Object.fromEntries(LES.map(l=>[l.id,l]));
const ZIG=[0,55,85,55,0,-55,-85,-55];
const lockSvg='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5zm-3 8V7a3 3 0 0 1 6 0v3z"/></svg>';
const medalSvg='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h4l1 4 1-4h4l-3.2 6.4A7 7 0 1 1 7.2 8.4zM12 10a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0 2 1.2 2.3 2.6.4-1.9 1.8.5 2.5-2.4-1.2-2.3 1.2.4-2.5-1.9-1.8 2.6-.4z"/></svg>';
const flameSvg='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2s5 4.5 5 10a5 5 0 0 1-10 0c0-2.4 1.3-4.2 2.4-5.2.2 1.8 1.1 3 2.3 3.4C11 7.6 12 2 12 2zm0 12.5c-1.1.9-1.6 1.8-1.6 2.7a1.6 1.6 0 0 0 3.2 0c0-.9-.5-1.8-1.6-2.7z"/></svg>';
const starsHtml=n=>[0,1,2].map(i=>'<i class="star-ico'+(i<n?'':' off')+'"></i>').join('');
const unlocked=l=>P.guru||l.idx===0||!!P.lessons[LES[l.idx-1].id];

/* ---------- daily puzzle ---------- */
const DAILY_IDS=['fork','pin','terbuka','cct','mate2','gambit','ujian1','ujian2','ujian3','sah','seri','mateq','mater','nilai'];
const BANK=[];LES.forEach(l=>{if(!DAILY_IDS.includes(l.id))return;l.steps.forEach(st=>{if(st.type==='puzzle'&&st.fen&&!st.orient&&(st.acceptMate||st.acceptSan||(st.line&&st.line.length>=3)))BANK.push(st)})});
const dstr=d=>d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
const today=()=>dstr(new Date());
const yesterday=()=>{const d=new Date();d.setDate(d.getDate()-1);return dstr(d)};
function dailyStep(){const d=new Date();const n=Math.floor(Date.UTC(d.getFullYear(),d.getMonth(),d.getDate())/864e5);const st=BANK[n%BANK.length];
  return Object.assign({},st,{title:'Teka-teki '+d.toLocaleDateString('ms-MY',{day:'numeric',month:'long'}),say:'Teka-teki baharu setiap hari. Selesaikan untuk kekalkan streak!<br>'+(st.say||'')})}
const streakNow=()=>{const D=P.daily||{};return(D.last===today()||D.last===yesterday())?(D.streak||0):0};

/* ---------- map ---------- */
function renderMap(){
  const next=LES.find(l=>!P.lessons[l.id]&&unlocked(l));const done=LES.filter(l=>P.lessons[l.id]).length;
  const dDone=(P.daily||{}).last===today(),sk=streakNow();
  let h='<section class="hero"><div class="avatar"><i class="pc wN"></i></div><div><h1>'+(done?'Selamat kembali!':'Jom main catur!')+'</h1><p>Saya Pak Kuda. Kita belajar catur langkah demi langkah: kenal buah, belajar taktik, kutip bintang dan kumpul XP.</p>'+
   '<div class="hero-row">'+(next?'<button class="cta" type="button" data-go="'+next.id+'">'+(done?'Sambung: ':'Mula: ')+next.title+'</button>':'<span class="pill">Semua pelajaran selesai!</span>')+
   '<div class="meter" aria-label="Kemajuan"><span style="width:'+Math.round(done/LES.length*100)+'%"></span></div><small class="meter-lbl">'+done+'/'+LES.length+' pelajaran</small></div></div></section>';
  h+='<section class="quick">'+
   '<button class="qcard'+(dDone?' done':'')+'" type="button" id="go-daily"><span class="qico flame">'+flameSvg+'</span><span><b>Teka-teki Hari Ini</b><small>'+(dDone?'Selesai! Datang lagi esok.':'Satu teka-teki baharu setiap hari')+'</small></span><span class="qnum">'+sk+'<small>hari</small></span></button>'+
   '<button class="qcard" type="button" id="go-game"><span class="qico"><i class="pc bK"></i></span><span><b>Main lawan Pak Kuda</b><small>Permainan penuh: mudah, sederhana atau sukar</small></span><span class="qnum">'+(P.wins||0)+'<small>menang</small></span></button></section>';
  let zi=0;
  for(const t of TAHAP){const ls=LES.filter(l=>l.tahap===t.n),dn=ls.filter(l=>P.lessons[l.id]).length;
    h+='<section class="tahap"><div class="tahap-head"><small>Tahap '+t.n+'</small><h2>'+t.name+'</h2><span>'+t.note+' · '+dn+'/'+ls.length+'</span></div><div class="path">';
    for(const l of ls){const x=l.exam?0:ZIG[zi++%ZIG.length],d=P.lessons[l.id],lk=!unlocked(l),cls=(d?'done':lk?'locked':'open')+(l.exam?' exam':'');
      const sub=d?(l.exam?'Lulus · '+d.score:starsHtml(d.stars)):lk?'Terkunci':(l.exam?l.steps.length+' soalan · lulus 70%':l.steps.length+' langkah · '+l.xp+' XP');
      const ico=l.exam?'<span class="medal">'+medalSvg+'</span>':'<i class="pc '+l.icon+'"></i>';
      h+='<div class="stop '+cls+'" style="--x:'+x+'px"><button class="node" type="button" '+(lk?'disabled':'data-go="'+l.id+'"')+' aria-label="'+l.title+(lk?', terkunci':'')+'">'+ico+(lk?'<span class="lock">'+lockSvg+'</span>':'')+'</button><span class="lbl">'+l.title+'</span><span class="sub">'+sub+'</span></div>'}
    h+='</div></section>'}
  $('#view-map').innerHTML=h;$('#guru').checked=!!P.guru;
  $('#go-daily').onclick=openDaily;$('#go-game').onclick=openGame}
$('#view-map').addEventListener('click',e=>{const b=e.target.closest('[data-go]');if(b)openLesson(BYID[b.dataset.go])});

/* ---------- lesson runner ---------- */
let cur=null;
const VIEWS=['map','lesson','game'];
function show(v){VIEWS.forEach(x=>$('#view-'+x).hidden=x!==v);window.scrollTo(0,0)}
function goMap(){if(cur)cur.clear();cur=null;if(G)G.stop();$('#modal').hidden=true;renderMap();refreshStats();show('map')}
function openDaily(){openLesson({id:'harian',daily:true,title:'Teka-teki Harian',steps:[dailyStep()],xp:15})}
function openLesson(L){
  const V=$('#view-lesson');const id=L.id;
  V.innerHTML='<div class="lesson-bar"><button class="ghost" type="button" id="back">← Peta</button><h2>'+L.title+'</h2><div class="prog" id="prog"></div></div>'+
   '<div class="lesson"><div class="board-wrap"><div id="bw"></div><p class="turn" id="turn"></p></div><div class="panel">'+
   (L.exam?'<p class="exam-note">Ujian: setiap soalan ada <b>satu peluang</b>. Lulus jika betul sekurang-kurangnya 70%.</p>':'')+
   '<h3 class="step-title" id="stitle"></h3>'+
   '<div class="coach"><div class="avatar sm"><i class="pc wN"></i></div><div class="bubble"><span class="who">Pak Kuda</span><p id="say"></p></div></div>'+
   '<div class="task"><small>Tugasan</small><div class="row"><span id="task"></span><span class="counter" id="counter"></span></div><div class="opts" id="opts"></div></div>'+
   '<div class="status" id="status" role="status" aria-live="polite"></div>'+
   '<div class="actions"><span id="acts" class="actions"></span><span class="spacer"></span><button class="cta" id="next" type="button" disabled>Teruskan</button></div>'+
   '</div></div>';
  show('lesson');
  let step=0,mistakes=0,timers=[],board=null;const failed=new Set();
  const examNote=()=>L.exam&&failed.has(step)?' Soalan ini dikira salah. Tekan Teruskan.':'';
  const c={
    newBoard(o){board=Board($('#bw'),o);const st=L.steps[step];
      $('#turn').textContent=st.fen?(st.fen.split(' ')[1]==='w'?'Putih untuk bergerak':'Hitam untuk bergerak'):'';return board},
    board:()=>board,
    later(fn,ms){timers.push(setTimeout(fn,ms))},
    clear(){timers.forEach(clearTimeout);timers=[]},
    status(t,k){const s=$('#status');s.className='status'+(k?' '+k:'');s.textContent=t+(k==='bad'&&t?examNote():'')},
    statusHtml(h){$('#status').innerHTML=h+($('#status').classList.contains('bad')?examNote():'')},
    counter(t){$('#counter').textContent=t},
    task(h){$('#task').innerHTML=h},
    opts(){return $('#opts')},
    mistake(n){mistakes+=n||1;if(L.exam){failed.add(step);$('#next').disabled=false}},
    actions(list){const a=$('#acts');a.innerHTML='';list.forEach(x=>{if(L.exam&&x.label==='Petunjuk')return;const bt=document.createElement('button');bt.type='button';bt.className='ghost';bt.textContent=x.label;bt.onclick=x.fn;a.appendChild(bt)})},
    done(){$('#next').disabled=false}};
  cur=c;$('#back').onclick=goMap;
  function load(){c.clear();const st=L.steps[step];
    $('#prog').innerHTML=L.steps.map((_,i)=>'<span class="'+(L.exam&&failed.has(i)?'miss':i<step?'on':i===step?'cur':'')+'"></span>').join('');
    $('#stitle').textContent=st.title;$('#say').innerHTML=st.say||'';c.task(st.task||'');$('#opts').innerHTML='';
    c.counter('');c.status('','');c.actions([]);$('#next').disabled=true;$('#next').textContent=step===L.steps.length-1?'Selesai':'Teruskan';
    TYPES[st.type](st,c)}
  $('#next').onclick=()=>{if(step<L.steps.length-1){step++;load();$('#stitle').scrollIntoView({block:'nearest'})}else finish()};
  function modal(html){const m=$('#modal');m.innerHTML='<div class="card" role="dialog" aria-modal="true" aria-labelledby="ct">'+html+'</div>';m.hidden=false;return m}
  function finish(){c.clear();SFX.win();
    if(L.daily){const D=P.daily||{};let gained=0;if(D.last!==today()){D.streak=(D.last===yesterday()?(D.streak||0):0)+1;D.last=today();gained=L.xp;P.xp+=gained}P.daily=D;save();refreshStats();
      modal('<span class="qico flame big">'+flameSvg+'</span><h2 id="ct">'+D.streak+' hari berturut-turut!</h2><p>Teka-teki hari ini selesai. Datang lagi esok untuk teka-teki baharu.</p>'+(gained?'<div class="xp">+'+gained+' XP</div>':'')+'<div class="actions" style="justify-content:center"><button class="cta" type="button" id="m-map">Ke peta</button></div>');
      $('#m-map').onclick=goMap;$('#m-map').focus();return}
    if(L.exam){const q=L.steps.length,score=q-failed.size,pass=score>=Math.ceil(q*0.7);
      if(!pass){modal('<div class="big-stars">'+starsHtml(0)+'</div><h2 id="ct">Hampir!</h2><p>Markah awak <b>'+score+'/'+q+'</b>. Perlu sekurang-kurangnya '+Math.ceil(q*0.7)+' untuk lulus. Ulang kaji pelajaran tahap ini dan cuba lagi.</p><div class="actions" style="justify-content:center"><button class="cta" type="button" id="m-retry">Cuba lagi</button><button class="ghost" type="button" id="m-map">Ke peta</button></div>');
        $('#m-retry').onclick=()=>{$('#modal').hidden=true;openLesson(L)};$('#m-map').onclick=goMap;$('#m-retry').focus();return}
      const stars=score===q?3:score>=q-1?2:1,prev=P.lessons[id],gained=prev?Math.round(L.xp*0.2):L.xp;P.xp+=gained;
      P.lessons[id]={stars:Math.max(stars,prev?prev.stars:0),score:score+'/'+q,date:today()};save();refreshStats();
      const t=TAHAP.find(t=>t.n===L.tahap),nx=LES[L.idx+1];
      const m=modal('<div class="cert"><small>Sijil ChessFlow</small><h2 id="ct">Tahap '+t.n+': '+t.name+'</h2><p>Dengan ini disahkan bahawa</p><input id="nama" class="cert-name" type="text" maxlength="40" placeholder="Tulis nama awak" aria-label="Nama"><p>telah lulus Ujian Tahap '+t.n+' dengan markah <b>'+score+'/'+q+'</b>.</p><div class="big-stars">'+starsHtml(stars)+'</div><small class="cert-date">'+new Date().toLocaleDateString('ms-MY',{day:'numeric',month:'long',year:'numeric'})+' · Pak Kuda</small></div>'+
        '<div class="xp">+'+gained+' XP</div><div class="actions" style="justify-content:center">'+(nx?'<button class="cta" type="button" id="m-next">Seterusnya: '+nx.title+'</button>':'')+'<button class="ghost" type="button" id="m-map">Ke peta</button></div>');
      const nmEl=$('#nama');nmEl.value=P.name||'';nmEl.oninput=()=>{P.name=nmEl.value;save()};
      $('#m-map').onclick=goMap;if(nx)$('#m-next').onclick=()=>{m.hidden=true;openLesson(nx)};return}
    const stars=mistakes<=1?3:mistakes<=4?2:1,prev=P.lessons[id];
    const gained=prev?Math.round(L.xp*0.2):L.xp;P.xp+=gained;P.lessons[id]={stars:Math.max(stars,prev?prev.stars:0)};save();refreshStats();
    const nx=LES[L.idx+1];
    const m=modal('<div class="big-stars">'+starsHtml(stars)+'</div><h2 id="ct">Tahniah!</h2><p>Awak habiskan <b>'+L.title+'</b>'+(mistakes?' dengan '+mistakes+' kesilapan kecil.':' tanpa sebarang kesilapan!')+'</p><div class="xp">+'+gained+' XP</div>'+(L.tip?'<div class="tip">'+L.tip+'</div>':'')+
      '<div class="actions" style="justify-content:center">'+(nx?'<button class="cta" type="button" id="m-next">Seterusnya: '+nx.title+'</button>':'')+'<button class="ghost" type="button" id="m-map">Ke peta</button></div>');
    $('#m-map').onclick=goMap;if(nx)$('#m-next').onclick=()=>{m.hidden=true;openLesson(nx)};($('#m-next')||$('#m-map')).focus()}
  load()}

/* ---------- Stockfish (Web Worker) ---------- */
let SF=null,sfCb=null,sfFailed=false;
function sfInit(){if(SF||sfFailed)return;try{SF=new Worker('stockfish.js');
  SF.onmessage=e=>{const l=typeof e.data==='string'?e.data:'';if(l.startsWith('bestmove')&&sfCb){const cb=sfCb;sfCb=null;cb(l.split(' ')[1])}};
  SF.onerror=()=>{sfFailed=true;SF=null;if(sfCb){const cb=sfCb;sfCb=null;cb(null)}};
  SF.postMessage('uci');SF.postMessage('isready')}catch(e){sfFailed=true;SF=null}}
function sfBest(fen,skill,depth,cb){if(!SF)return cb(null);sfCb=cb;SF.postMessage('setoption name Skill Level value '+skill);SF.postMessage('position fen '+fen);SF.postMessage('go depth '+depth);
  setTimeout(()=>{if(sfCb===cb){sfCb=null;SF.postMessage('stop');cb(null)}},9000)}

/* ---------- full game ---------- */
const LEVELS=[{n:'Mudah',xp:20},{n:'Sederhana',xp:50,skill:0,depth:2},{n:'Sukar',xp:100,skill:6,depth:8}];
let G=null;
function openGame(){sfInit();show('game');const V=$('#view-game');
  const pref=P.gamePref||{color:'w',level:0};
  V.innerHTML='<div class="lesson-bar"><button class="ghost" type="button" id="gback">← Peta</button><h2>Main lawan Pak Kuda</h2></div>'+
   '<div class="lesson"><div class="board-wrap"><div id="gbw"></div><p class="turn" id="gturn"></p></div><div class="panel">'+
   '<div class="setup"><div class="seg" role="group" aria-label="Warna"><span class="seg-l">Warna</span><button type="button" data-c="w">Putih</button><button type="button" data-c="b">Hitam</button><button type="button" data-c="r">Rawak</button></div>'+
   '<div class="seg" role="group" aria-label="Tahap"><span class="seg-l">Tahap</span>'+LEVELS.map((l,i)=>'<button type="button" data-l="'+i+'">'+l.n+'</button>').join('')+'</div>'+
   '<button class="cta" type="button" id="gnew">Permainan baru</button></div>'+
   '<div class="status" id="gstatus" role="status" aria-live="polite"></div>'+
   '<div class="moves"><small>Langkah</small><ol id="gmoves"></ol></div>'+
   '<div class="actions"><button class="ghost" type="button" id="gundo">Undur</button><button class="ghost" type="button" id="ghint">Petunjuk</button><span class="spacer"></span><button class="ghost" type="button" id="gresign">Mengaku kalah</button></div>'+
   '</div></div>';
  let color=pref.color,level=pref.level;
  const segs=()=>{V.querySelectorAll('[data-c]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.c===color)));V.querySelectorAll('[data-l]').forEach(b=>b.setAttribute('aria-pressed',String(+b.dataset.l===level)))};
  V.querySelectorAll('[data-c]').forEach(b=>b.onclick=()=>{color=b.dataset.c;segs()});
  V.querySelectorAll('[data-l]').forEach(b=>b.onclick=()=>{level=+b.dataset.l;segs()});segs();
  let g,b,user,over,busy,sel,tm=[];
  const st=(t,k)=>{const s=$('#gstatus');s.className='status'+(k?' '+k:'');s.textContent=t};
  const later=(f,ms)=>tm.push(setTimeout(f,ms));
  const moves=()=>{const h=g.history();let out='';for(let i=0;i<h.length;i+=2)out+='<li><span>'+h[i]+'</span><span>'+(h[i+1]||'')+'</span></li>';const ol=$('#gmoves');ol.innerHTML=out;ol.scrollTop=ol.scrollHeight;
    $('#gturn').textContent=over?'':(g.turn()===user?'Giliran awak':'Pak Kuda sedang berfikir…')};
  const end=()=>{if(!g.game_over())return false;over=true;
    if(g.in_checkmate()){if(g.turn()!==user){const xp=LEVELS[level].xp;P.xp+=xp;P.wins=(P.wins||0)+1;save();refreshStats();st('Sah mati! Awak menang lawan Pak Kuda ('+LEVELS[level].n+'). +'+xp+' XP','good');SFX.win()}
      else{st('Sah mati. Pak Kuda menang kali ini. Cuba lagi!','bad');SFX.bad()}}
    else st(g.in_stalemate()?'Stalemate. Permainan seri.':g.in_threefold_repetition()?'Ulangan tiga kali. Seri.':g.insufficient_material()?'Buah tak cukup untuk sah mati. Seri.':'Seri (peraturan 50 langkah).','info');
    moves();return true};
  const apply=m=>{const r=g.move(m);b.applyMove(r);b.mark({last:null,hl:[S(r.from),S(r.to)],sel:null,dots:[]});b.arrows([]);later(()=>b.quiet(g.fen()),420);moves();return r};
  const botMove=()=>{if(over)return;busy=true;moves();const L=LEVELS[level],fen=g.fen();
    const fallback=()=>bot(g,'defend',user,level===0?260:40);
    const go=mv=>{later(()=>{if(over||!G)return;const r=apply(mv||fallback());SFX.move();busy=false;if(!end()){if(g.in_check())st('Sah! Selamatkan Raja awak.','bad');else st('','')}moves()},level===0?500:150)};
    if(L.skill==null||!SF)go(null);
    else sfBest(fen,L.skill,L.depth,u=>go(u?{from:u.slice(0,2),to:u.slice(2,4),promotion:u[4]}:null))};
  function onTap(i){if(over||busy||g.turn()!==user)return;const sq=nm(i),p=g.get(sq);
    if(p&&p.color===user){sel=sq;b.mark({sel:i,dots:g.moves({square:sq,verbose:true}).map(m=>S(m.to))});return}
    if(!sel)return;const legal=g.moves({square:sel,verbose:true}).filter(m=>m.to===sq);if(!legal.length)return;
    apply({from:sel,to:sq,promotion:legal[0].promotion?'q':undefined});sel=null;SFX.move();st('','');if(!end())botMove()}
  function start(){tm.forEach(clearTimeout);tm=[];user=color==='r'?(Math.random()<.5?'w':'b'):color;P.gamePref={color,level};save();
    g=new Chess();b=Board($('#gbw'),user);b.setFen(g.fen());b.on(onTap);over=false;busy=false;sel=null;
    st('Awak main '+(user==='w'?'Putih':'Hitam')+' · tahap '+LEVELS[level].n+'.'+(level>0&&sfFailed?' (Enjin kuat tidak dapat dimuatkan, Pak Kuda guna otak sendiri.)':''),'info');moves();
    if(user==='b')botMove()}
  $('#gnew').onclick=start;$('#gback').onclick=goMap;
  $('#gundo').onclick=()=>{if(busy||!g.history().length)return;g.undo();if(g.turn()!==user)g.undo();over=false;b.quiet(g.fen());b.mark({hl:[],sel:null,dots:[]});b.arrows([]);sel=null;st('Langkah diundur.','info');moves()};
  $('#ghint').onclick=()=>{if(busy||over||g.turn()!==user)return;busy=true;st('Pak Kuda sedang fikir petunjuk…','info');
    const show=mv=>{busy=false;if(!mv)return st('Tiada petunjuk.','info');b.arrows([{from:S(mv.from),to:S(mv.to),kind:'path'}]);st('Cuba langkah anak panah ini.','info')};
    if(SF)sfBest(g.fen(),20,10,u=>show(u?{from:u.slice(0,2),to:u.slice(2,4)}:bot(g,'defend',g.turn()==='w'?'b':'w',1)));else show(bot(g,'defend',g.turn()==='w'?'b':'w',1))};
  $('#gresign').onclick=()=>{if(over)return;over=true;st('Awak mengaku kalah. Tekan "Permainan baru" untuk cuba lagi.','bad');moves()};
  G={stop(){tm.forEach(clearTimeout);tm=[];G=null}};
  start()}

/* ---------- boot ---------- */
$('#home').onclick=goMap;
$('#play-top').onclick=openGame;
$('#snd').onclick=()=>{soundOn=!soundOn;try{localStorage.setItem(KEY+'-snd',soundOn?'1':'0')}catch(e){}setSndLabel();SFX.good()};
$('#guru').onchange=e=>{P.guru=e.target.checked;save();renderMap()};
let resetArm=false;
$('#reset').onclick=e=>{if(!resetArm){resetArm=true;e.target.textContent='Tekan sekali lagi untuk padam';setTimeout(()=>{resetArm=false;e.target.textContent='Padam kemajuan'},3000);return}
  P={xp:0,lessons:{},guru:P.guru};save();resetArm=false;e.target.textContent='Padam kemajuan';goMap()};
setSndLabel();renderMap();refreshStats();
if(!HAS_CHESS)$('#view-map').insertAdjacentHTML('afterbegin','<p class="status bad">Enjin catur gagal dimuatkan. Sesetengah pelajaran tidak akan berfungsi.</p>');
})();
