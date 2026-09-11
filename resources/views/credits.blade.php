<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#d9d3c3">
<title>Tim SAPTO</title>
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=playfair-display:700|eb-garamond:400,400i,600" rel="stylesheet">
<style>
  :root{
    --ink: #2a2016;
    --ink-soft: #6b6047;
    --ink-faint: #8a7d68;
    --paper: #faf6ec;
    --rule: rgba(42,32,22,.22);
    --stamp: #a3372b;
  }

  *{ box-sizing:border-box; }

  html,body{ margin:0; }

  body{
    min-height:100vh;
    display:grid;
    place-items:center;
    padding: 3.5rem 1.25rem;
    background: radial-gradient(130% 110% at 50% 15%, #e7e1d1, #cec6b1 130%);
    color: var(--ink);
    font-family:'EB Garamond', Georgia, serif;
  }

  /* Kertas: kartu tunggal, sedikit dimiringkan seperti diletakkan di atas
     meja, terangkat lewat bayangan berlapis (bukan border keras). Hover
     "diambil" -- lurus sedikit dan naik -- satu-satunya interaksi di
     halaman ini, murni CSS. */
  .paper{
    position:relative;
    width:min(30rem, 100%);
    padding: 2.75rem 2.25rem 2.5rem;
    background: var(--paper);
    box-shadow:
      0 1px 1px rgba(30,20,10,.15),
      0 10px 25px -15px rgba(20,14,8,.35),
      0 30px 60px -25px rgba(20,14,8,.5);
    transform: rotate(-.6deg);
    transition: transform .35s ease, box-shadow .35s ease;
    opacity: 0;
    animation: paper-in .55s ease forwards;
  }
  .paper:hover{
    transform: rotate(0deg) translateY(-3px);
    box-shadow:
      0 1px 1px rgba(30,20,10,.15),
      0 14px 30px -15px rgba(20,14,8,.4),
      0 40px 70px -25px rgba(20,14,8,.55);
  }
  @keyframes paper-in{
    from{ opacity:0; transform:translateY(14px) rotate(-.6deg); }
    to{ opacity:1; transform:translateY(0) rotate(-.6deg); }
  }

  /* Serat kertas halus -- dua lapis radial-gradient kecil, bukan foto
     tekstur, supaya halaman tetap ringan. */
  .paper::before{
    content:'';
    position:absolute; inset:0;
    background-image:
      radial-gradient(rgba(42,32,22,.05) .5px, transparent .5px),
      radial-gradient(rgba(42,32,22,.03) .5px, transparent .5px);
    background-size: 3px 3px, 5px 5px;
    background-position: 0 0, 1px 2px;
    pointer-events:none;
  }

  /* Lipatan tipis di tengah, seperti surat yang pernah dilipat dua. */
  .paper::after{
    content:'';
    position:absolute; left:0; right:0; top:50%;
    height:16px; margin-top:-8px;
    background: linear-gradient(to bottom,
      transparent, rgba(20,14,8,.05) 45%, rgba(20,14,8,.08) 50%,
      rgba(20,14,8,.05) 55%, transparent);
    pointer-events:none;
  }

  /* Selotip kecil di atas kertas -- kesan ditempel, bukan cuma dicetak. */
  .tape{
    position:absolute; top:-14px; left:50%;
    width:6.5rem; height:2.1rem;
    background: rgba(228,216,176,.55);
    box-shadow: 0 2px 4px rgba(20,14,8,.12);
    transform: translateX(-50%) rotate(-2deg);
  }

  .letterhead{
    position:relative; z-index:1;
    text-align:center;
    padding-bottom: 1.4rem;
    margin-bottom: 1.75rem;
    border-bottom: 1px solid var(--rule);
  }
  .eyebrow{
    margin:0 0 .5rem;
    font-size:.68rem;
    letter-spacing:.22em;
    text-transform:uppercase;
    color: var(--ink-faint);
  }
  .title{
    margin:0;
    font-family:'Playfair Display', serif;
    font-weight:700;
    font-size: clamp(2.2rem, 8vw, 2.9rem);
    letter-spacing:.01em;
    color: var(--ink);
  }
  .subtitle{
    margin:.4rem 0 0;
    font-size:.8rem;
    letter-spacing:.03em;
    color: var(--ink-soft);
  }

  .intro{
    position:relative; z-index:1;
    margin: 0 0 1.5rem;
    font-style: italic;
    font-size: .95rem;
    color: var(--ink-soft);
  }

  .credits{
    position:relative; z-index:1;
    list-style:none;
    margin: 0 0 2rem;
    padding:0;
  }
  .credits li{
    display:flex;
    align-items:baseline;
    justify-content:space-between;
    gap:1rem;
    padding:.65rem 0;
    border-bottom: 1px dotted var(--rule);
  }
  .credits li:last-child{ border-bottom:0; }
  .who strong{
    display:block;
    font-size:1.02rem;
    font-weight:600;
    color: var(--ink);
  }
  .who small{
    display:block;
    margin-top:.15rem;
    font-size:.7rem;
    letter-spacing:.05em;
    color: var(--ink-faint);
    font-variant-numeric: tabular-nums;
  }
  .role{
    flex:none;
    padding-top:.15rem;
    font-size:.68rem;
    letter-spacing:.13em;
    text-transform:uppercase;
    color: var(--ink-faint);
  }

  .closing{
    position:relative; z-index:1;
    display:flex;
    justify-content:space-between;
    gap:1rem;
    padding-top:1.25rem;
    border-top: 1px solid var(--rule);
    font-size:.7rem;
    letter-spacing:.03em;
    color: var(--ink-faint);
  }

  /* Cap merah -- lingkaran ganda dengan teks melengkung di antara ring,
     dijatuhkan sedikit miring di pojok kanan bawah, menimpa tepi kertas. */
  .stamp{
    position:absolute;
    right:-1.6rem;
    bottom:-1.4rem;
    width:10.5rem; height:10.5rem;
    color: var(--stamp);
    opacity:0;
    mix-blend-mode: multiply;
    transform: rotate(-13deg) scale(2.2);
    animation: stamp-in .5s cubic-bezier(.18,1.4,.4,1) .5s forwards;
  }
  @keyframes stamp-in{
    0%{ opacity:0; transform: rotate(-13deg) scale(2.2); }
    55%{ opacity:.9; }
    100%{ opacity:.82; transform: rotate(-13deg) scale(1); }
  }
  .stamp svg{ width:100%; height:100%; display:block; }

  @media (max-width:480px){
    .paper{ padding: 2.25rem 1.5rem 2rem; }
    .stamp{ width:8rem; height:8rem; right:-.75rem; bottom:-.9rem; }
    .closing{ flex-direction:column; gap:.3rem; }
  }

  @media (prefers-reduced-motion: reduce){
    .paper{ animation:none; opacity:1; transition:none; }
    .paper:hover{ transform: rotate(-.6deg); box-shadow: 0 1px 1px rgba(30,20,10,.15), 0 10px 25px -15px rgba(20,14,8,.35), 0 30px 60px -25px rgba(20,14,8,.5); }
    .stamp{ animation:none; opacity:.82; transform: rotate(-13deg) scale(1); }
  }
</style>
</head>
<body>

<main class="paper">
  <div class="tape" aria-hidden="true"></div>

  <header class="letterhead">
    <p class="eyebrow">Universitas Sriwijaya</p>
    <h1 class="title">SAPTO</h1>
    <p class="subtitle">Sistem Akreditasi Perguruan Tinggi Online</p>
  </header>

  <p class="intro">Dicatat dengan bangga, tim yang membangun dan merawat sistem ini:</p>

  <ul class="credits">
    <li>
      <span class="who"><strong>Bemby Dwigantara Anom</strong><small>09011182429015</small></span>
      <span class="role">Frontend</span>
    </li>
    <li>
      <span class="who"><strong>Abdul Karim</strong><small>09011282429038</small></span>
      <span class="role">Backend</span>
    </li>
    <li>
      <span class="who"><strong>Arya Kusuma</strong><small>09011282429035</small></span>
      <span class="role">DevOps</span>
    </li>
    <li>
      <span class="who"><strong>Haziratul Qudsi</strong><small>09011282429040</small></span>
      <span class="role">Backend</span>
    </li>
  </ul>

  <footer class="closing">
    <span>No. Ref: SAPTO/TIM/2026</span>
    <span>Palembang, {{ now()->format('d.m.Y') }}</span>
  </footer>

  <div class="stamp" aria-hidden="true">
    <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
      <defs>
        <path id="stampArcTop" d="M 22,100 A 78,78 0 0 1 178,100" fill="none"/>
        <path id="stampArcBottom" d="M 22,100 A 78,78 0 0 0 178,100" fill="none"/>
        <filter id="stampGrain" x="-20%" y="-20%" width="140%" height="140%">
          <feTurbulence type="fractalNoise" baseFrequency=".9" numOctaves="2" seed="7" result="n"/>
          <feColorMatrix in="n" type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 .55 0"/>
        </filter>
      </defs>

      <circle cx="100" cy="100" r="92" fill="none" stroke="currentColor" stroke-width="4"/>
      <circle cx="100" cy="100" r="70" fill="none" stroke="currentColor" stroke-width="1.5"/>
      <circle cx="22" cy="100" r="2.4" fill="currentColor"/>
      <circle cx="178" cy="100" r="2.4" fill="currentColor"/>

      <text font-size="11" letter-spacing="2.5" fill="currentColor">
        <textPath href="#stampArcTop" xlink:href="#stampArcTop" startOffset="50%" text-anchor="middle">UNIVERSITAS SRIWIJAYA</textPath>
      </text>
      <text font-size="9.5" letter-spacing="2" fill="currentColor">
        <textPath href="#stampArcBottom" xlink:href="#stampArcBottom" startOffset="50%" text-anchor="middle">SISTEM AKREDITASI · BAN-PT</textPath>
      </text>

      <text x="100" y="94" text-anchor="middle" font-size="30" font-weight="700" fill="currentColor" font-family="'Playfair Display', serif">SAPTO</text>
      <text x="100" y="116" text-anchor="middle" font-size="9.5" letter-spacing="3" fill="currentColor">TERVERIFIKASI</text>

      <rect x="8" y="8" width="184" height="184" filter="url(#stampGrain)" opacity=".3" style="mix-blend-mode:multiply"/>
    </svg>
  </div>
</main>

</body>
</html>
