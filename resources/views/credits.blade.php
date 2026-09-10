<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#000000">
<title>¯\_(ツ)_/¯ — SAPTO</title>
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=share-tech-mono|vt323" rel="stylesheet">
<style>
  :root{
    --bg: #000000;
    --green: #2bff88;
    --green-dim: rgba(43,255,136,.55);
    --red: #ff3b30;
    --panel: rgba(0,0,0,.82);
    --border: rgba(43,255,136,.35);
  }

  *{ box-sizing:border-box; }

  html,body{
    margin:0;
    min-height:100vh;
    background:var(--bg);
    color:var(--green);
    font-family:'Share Tech Mono', ui-monospace, monospace;
  }

  body{
    position:relative;
    display:grid;
    place-items:center;
    padding:2.5rem 1.1rem;
    overflow-x:hidden;
  }

  canvas#matrix{
    position:fixed; inset:0; z-index:0;
    display:block;
  }

  .vignette{
    position:fixed; inset:0; z-index:1; pointer-events:none;
    background: radial-gradient(120% 90% at 50% 45%, transparent 40%, rgba(0,0,0,.75) 100%);
  }

  .scanlines{
    position:fixed; inset:0; z-index:2; pointer-events:none;
    background: repeating-linear-gradient(to bottom, rgba(43,255,136,.035) 0 1px, transparent 1px 3px);
    opacity:.5;
  }

  .flicker{
    position:fixed; inset:0; z-index:3; pointer-events:none;
    background: rgba(43,255,136,.02);
    animation: flicker 6.5s infinite;
  }
  @keyframes flicker{
    0%, 96%, 100%{ opacity:0; }
    97%{ opacity:1; }
    98%{ opacity:.2; }
    99%{ opacity:.7; }
  }

  .panel{
    position:relative;
    z-index:4;
    width:100%;
    max-width:36rem;
    background:var(--panel);
    border:1px solid var(--border);
    padding:2.1rem 1.9rem 1.8rem;
    box-shadow: 0 0 60px rgba(43,255,136,.08), 0 40px 90px -30px rgba(0,0,0,.9);
    opacity:0;
    transform:translateY(18px);
    animation: rise .5s ease forwards;
  }

  .corner{ position:absolute; width:16px; height:16px; border:0 solid var(--green); }
  .corner.tl{ top:-1px; left:-1px; border-top-width:2px; border-left-width:2px; }
  .corner.tr{ top:-1px; right:-1px; border-top-width:2px; border-right-width:2px; }
  .corner.bl{ bottom:-1px; left:-1px; border-bottom-width:2px; border-left-width:2px; }
  .corner.br{ bottom:-1px; right:-1px; border-bottom-width:2px; border-right-width:2px; }

  .warn{
    font-size:11px;
    letter-spacing:.12em;
    color:var(--red);
    text-align:center;
    margin-bottom:1.1rem;
    animation: blink-warn 1.6s step-end infinite;
  }
  @keyframes blink-warn{ 0%,49%{ opacity:1; } 50%,100%{ opacity:.25; } }

  .log{ font-size:12.5px; line-height:1.85; margin-bottom:1.3rem; }
  .l{
    opacity:0; transform:translateY(4px);
    animation: rise .35s ease forwards;
    animation-delay: calc(var(--d,0) * 160ms + .2s);
    color: rgba(43,255,136,.75);
  }
  .ok{ color:var(--green); font-weight:700; }

  .glitch{
    position:relative;
    margin: .2rem 0 .3rem;
    font-family:'VT323', monospace;
    font-size:clamp(3.6rem, 15vw, 6rem);
    letter-spacing:.04em;
    line-height:.9;
    text-align:center;
    color:var(--green);
    text-shadow: 0 0 14px rgba(43,255,136,.6), 0 0 40px rgba(43,255,136,.25);
  }
  .glitch::before, .glitch::after{
    content: attr(data-text);
    position:absolute; inset:0;
    background:transparent;
  }
  .glitch::before{
    color:#ff3b30;
    animation: glitch-1 3.4s infinite linear;
  }
  .glitch::after{
    color:#00e5ff;
    animation: glitch-2 2.9s infinite linear;
  }
  @keyframes glitch-1{
    0%, 92%{ clip-path: inset(0 0 100% 0); transform:translate(0,0); opacity:0; }
    93%{ clip-path: inset(10% 0 55% 0); transform:translate(-4px,1px); opacity:.85; }
    94.5%{ clip-path: inset(55% 0 8% 0); transform:translate(3px,-1px); opacity:.85; }
    96%{ clip-path: inset(30% 0 40% 0); transform:translate(-2px,2px); opacity:.5; }
    97%, 100%{ clip-path: inset(0 0 100% 0); transform:translate(0,0); opacity:0; }
  }
  @keyframes glitch-2{
    0%, 90%{ clip-path: inset(0 0 100% 0); transform:translate(0,0); opacity:0; }
    91%{ clip-path: inset(60% 0 5% 0); transform:translate(3px,-2px); opacity:.8; }
    92.5%{ clip-path: inset(5% 0 70% 0); transform:translate(-3px,1px); opacity:.8; }
    94%{ clip-path: inset(40% 0 30% 0); transform:translate(2px,2px); opacity:.5; }
    95%, 100%{ clip-path: inset(0 0 100% 0); transform:translate(0,0); opacity:0; }
  }

  .sub{
    text-align:center;
    font-size:10.5px;
    letter-spacing:.14em;
    text-transform:uppercase;
    color: rgba(43,255,136,.6);
    margin: 0 0 1.7rem;
  }

  .section-label{
    font-size:12px;
    color: rgba(43,255,136,.7);
    margin-bottom:.7rem;
    opacity:0; transform:translateY(4px);
    animation: rise .35s ease forwards;
    animation-delay: 1.05s;
  }

  .agents{ display:flex; flex-direction:column; gap:.5rem; margin-bottom:1.4rem; }
  .agent{
    display:flex; flex-wrap:wrap; align-items:center; gap:.7rem;
    padding:.55rem .7rem;
    border:1px solid var(--border);
    background: rgba(43,255,136,.03);
    opacity:0; transform:translateY(6px);
    animation: rise .35s ease forwards;
    animation-delay: calc(var(--d,0) * 110ms + 1.2s);
    transition: border-color .2s ease, background .2s ease;
  }
  .agent:hover{ border-color:var(--green); background: rgba(43,255,136,.08); }

  .dot{
    flex:none; width:8px; height:8px; border-radius:50%;
    background:var(--green);
    box-shadow: 0 0 6px var(--green);
    animation: pulse-dot 1.8s ease-in-out infinite;
  }
  @keyframes pulse-dot{ 0%,100%{ opacity:1; } 50%{ opacity:.35; } }

  .who{ flex:1 1 auto; min-width:0; }
  .name{
    display:block; font-size:13px; color:var(--green);
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
  .id{ display:block; font-size:10.5px; color: rgba(43,255,136,.55); margin-top:1px; }

  .tag{
    flex:none;
    font-size:10px; letter-spacing:.06em;
    color: rgba(43,255,136,.85);
    border:1px solid var(--border);
    padding:.2rem .5rem;
  }

  .prompt-line{
    font-size:13px; color:var(--green);
    opacity:0; transform:translateY(4px);
    animation: rise .35s ease forwards;
    animation-delay: 1.7s;
  }
  .cursor{
    display:inline-block; width:.55em; margin-left:2px;
    background:var(--green); color:var(--green);
    animation: blink 1s step-end infinite;
  }

  .footer{
    position:relative;
    z-index:4;
    margin-top:1.1rem;
    font-size:10.5px;
    letter-spacing:.06em;
    color: rgba(43,255,136,.45);
    text-align:center;
  }

  @keyframes rise{ to{ opacity:1; transform:translateY(0); } }
  @keyframes blink{ 0%,49%{ opacity:1; } 50%,100%{ opacity:0; } }

  :focus-visible{ outline:2px solid var(--green); outline-offset:2px; }

  body.sudo-mode .panel{
    border-color: rgba(255,59,48,.6);
    box-shadow: 0 0 60px rgba(255,59,48,.18), 0 40px 90px -30px rgba(0,0,0,.9);
    transition: border-color .3s ease, box-shadow .3s ease;
  }
  body.sudo-mode .corner{ border-color:#ff3b30; }

  .sudo-overlay{
    position:fixed; inset:0; z-index:50;
    display:flex; align-items:center; justify-content:center;
    background: rgba(0,0,0,.88);
    opacity:0; pointer-events:none;
    transition: opacity .25s ease;
    padding:1.5rem;
  }
  .sudo-overlay.show{ opacity:1; pointer-events:auto; }
  .sudo-box{
    max-width:26rem;
    border:1px solid rgba(255,59,48,.5);
    background:rgba(0,0,0,.92);
    padding:1.6rem 1.5rem;
    font-size:12.5px; line-height:1.8;
    color: rgba(255,59,48,.85);
    box-shadow: 0 0 60px rgba(255,59,48,.15);
  }
  .sudo-box p{ margin:0 0 .5rem; }
  .sudo-ok{ color:#ff3b30; font-weight:700; }
  .sudo-secret{ color:#fff; font-size:13.5px; margin:.7rem 0 !important; }
  .sudo-hint{ color: rgba(255,59,48,.5); font-size:10.5px; margin-top:.8rem !important; }

  @media (max-width:480px){
    .panel{ padding:1.7rem 1.3rem 1.5rem; }
    .agent{ padding:.5rem .6rem; }
    .tag{ margin-left: calc(8px + .7rem); }
  }

  @media (prefers-reduced-motion: reduce){
    .panel, .l, .agent, .section-label, .prompt-line{
      animation:none !important; opacity:1 !important; transform:none !important;
    }
    .glitch::before, .glitch::after{ display:none; }
    .cursor, .dot, .warn, .flicker{ animation:none !important; }
  }
</style>
</head>
<body>
<!-- psst... kalau nyasar sampai baca source, coba konami code: ↑ ↑ ↓ ↓ ← → ← → B A -->

<canvas id="matrix"></canvas>
<div class="vignette"></div>
<div class="scanlines"></div>
<div class="flicker"></div>

<main class="panel">
  <span class="corner tl"></span><span class="corner tr"></span>
  <span class="corner bl"></span><span class="corner br"></span>

  <div class="warn">[ UNAUTHORIZED ACCESS DETECTED ]</div>

  <div class="log">
    <p class="l" style="--d:0">&gt; initializing sapto_core.sys</p>
    <p class="l" style="--d:1">&gt; bypassing auth layer... <span class="ok">[OK]</span></p>
    <p class="l" style="--d:2">&gt; decrypting ban-pt_database.sql... <span class="ok">[OK]</span></p>
    <p class="l" style="--d:3">&gt; access level: <span class="ok">GUEST</span></p>
  </div>

  <h1 class="glitch" data-text="SAPTO">SAPTO</h1>
  <p class="sub">Sistem Akreditasi BAN-PT &middot; Universitas Sriwijaya</p>

  <div class="section-label">&gt; whoami --team</div>
  <div class="agents">
    <div class="agent" style="--d:0">
      <span class="dot"></span>
      <span class="who"><span class="name">Bemby Dwigantara Anom</span><span class="id">ID// 09011182429015</span></span>
      <span class="tag">[FRONTEND]</span>
    </div>
    <div class="agent" style="--d:1">
      <span class="dot"></span>
      <span class="who"><span class="name">Abdul Karim</span><span class="id">ID// 09011282429038</span></span>
      <span class="tag">[BACKEND]</span>
    </div>
    <div class="agent" style="--d:2">
      <span class="dot"></span>
      <span class="who"><span class="name">Arya Kusuma</span><span class="id">ID// 09011282429035</span></span>
      <span class="tag">[DEVOPS]</span>
    </div>
    <div class="agent" style="--d:3">
      <span class="dot"></span>
      <span class="who"><span class="name">Haziratul Qudsi</span><span class="id">ID// 09011282429040</span></span>
      <span class="tag">[BACKEND]</span>
    </div>
  </div>

  <p class="prompt-line">guest@sapto:~$ <span class="cursor">&#9608;</span></p>
</main>

<p class="footer">CONNECTION SECURE &middot; TRACE COMPLETE</p>

<div class="sudo-overlay" id="sudoOverlay" aria-hidden="true">
  <div class="sudo-box">
    <p>&gt; sudo su</p>
    <p>[sudo] password for guest: ********</p>
    <p class="sudo-ok">access escalated. welcome, root.</p>
    <p>&gt; echo $SECRET</p>
    <p class="sudo-secret">Kamu nemu easter egg di dalam easter egg. Niat banget dah &#128373;&#65039;</p>
    <p class="sudo-hint">tim SAPTO berterima kasih udah iseng sampai sini. klik di mana aja buat nutup.</p>
  </div>
</div>

<script>
(function(){
  var canvas = document.getElementById('matrix');
  var ctx = canvas.getContext('2d');
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var fontSize = 15;
  var columns, drops;

  function setup(){
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    columns = Math.floor(canvas.width / fontSize);
    drops = new Array(columns).fill(1);
  }
  setup();
  window.addEventListener('resize', setup);

  var chars = 'アイウエオカキクケコサシスセソタチツテト0123456789SAPTO'.split('');
  var matrixColor = '#2bff88';

  function draw(){
    ctx.fillStyle = 'rgba(0,0,0,0.08)';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = matrixColor;
    ctx.font = fontSize + 'px monospace';
    for (var i = 0; i < drops.length; i++){
      var text = chars[Math.floor(Math.random() * chars.length)];
      ctx.fillText(text, i * fontSize, drops[i] * fontSize);
      if (drops[i] * fontSize > canvas.height && Math.random() > 0.975){
        drops[i] = 0;
      }
      drops[i]++;
    }
  }

  if (reduce){
    ctx.fillStyle = '#000';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
  } else {
    setInterval(draw, 45);
  }

  /* console easter egg — for the ones who open devtools */
  console.log('%cSAPTO', 'color:#2bff88;font-size:32px;font-weight:bold;font-family:monospace;text-shadow:0 0 8px #2bff88;');
  console.log('%cKamu buka console ya? Dasar developer sejati. \uD83D\uDD75\uFE0F', 'color:#2bff88;font-family:monospace;font-size:12px;');
  console.log('%cPsst... coba masukin Konami Code: \u2191 \u2191 \u2193 \u2193 \u2190 \u2192 \u2190 \u2192 B A', 'color:#7b8494;font-family:monospace;font-size:11px;');

  /* konami code easter egg */
  var overlay = document.getElementById('sudoOverlay');
  var seq = ['ArrowUp','ArrowUp','ArrowDown','ArrowDown','ArrowLeft','ArrowRight','ArrowLeft','ArrowRight','b','a'];
  var pos = 0;
  var revertTimer, hideTimer;

  function triggerSudo(){
    if (!reduce){
      matrixColor = '#ff3b30';
      clearTimeout(revertTimer);
      revertTimer = setTimeout(function(){ matrixColor = '#2bff88'; }, 6000);
    }
    document.body.classList.add('sudo-mode');
    overlay.classList.add('show');
    clearTimeout(hideTimer);
    hideTimer = setTimeout(closeSudo, 6000);
  }

  function closeSudo(){
    overlay.classList.remove('show');
    document.body.classList.remove('sudo-mode');
  }

  overlay.addEventListener('click', closeSudo);

  document.addEventListener('keydown', function(e){
    var key = e.key.length === 1 ? e.key.toLowerCase() : e.key;
    if (key === seq[pos]){
      pos++;
      if (pos === seq.length){
        triggerSudo();
        pos = 0;
      }
    } else {
      pos = (key === seq[0]) ? 1 : 0;
    }
  });

  /* mobile fallback: tap the glitch title 7x fast */
  var titleEl = document.querySelector('.glitch');
  if (titleEl){
    var tapCount = 0, tapTimer;
    titleEl.addEventListener('click', function(){
      tapCount++;
      clearTimeout(tapTimer);
      tapTimer = setTimeout(function(){ tapCount = 0; }, 1200);
      if (tapCount >= 7){
        triggerSudo();
        tapCount = 0;
      }
    });
  }
})();
</script>

</body>
</html>
