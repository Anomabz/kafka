<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0c0e11">
<title>Tim SAPTO</title>
<link rel="icon" href="{{ asset('images/logo-unsri.webp') }}" type="image/webp">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
<style>
        /* Salinan token "Warna merek" dari resources/css/app.css (halaman ini
           tidak memuat app.css): desktop Biru Sriwijaya, HP Merah Marun. */
        :root { --brand: #2b4f8e; --brand-rgb: 43, 79, 142; --brand-ink: #12203a; --brand-ink-rgb: 18, 32, 58; --brand-muted: #4f6fa6; --brand-light: #cddcf6; --brand-soft: #9dbbef; --brand-soft-rgb: 157, 187, 239; }
        @media (max-width: 639.98px) {
            :root { --brand: #8a2a3a; --brand-rgb: 138, 42, 58; --brand-ink: #2a0b11; --brand-ink-rgb: 42, 11, 17; --brand-muted: #a8495a; --brand-light: #f1c9cf; --brand-soft: #eba3ae; --brand-soft-rgb: 235, 163, 174; }
        }
  /*
   * Easter egg, sengaja tidak ditautkan dari nav manapun (lihat
   * routes/web.php). Fixed-dark terlepas dari preferensi tema, sama
   * seperti panel login & halaman 503 -- ini bukan bagian dari app shell,
   * jadi tidak ada konteks data-theme user untuk diikuti. Semua warna
   * karena itu hardcoded, bukan var(--color-*).
   */
  * { box-sizing: border-box; }

  html, body { margin: 0; min-height: 100%; }

  body {
    font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, sans-serif;
    color: #e7e9ec;
    -webkit-font-smoothing: antialiased;
  }

  .wrap {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem 1.25rem;
    background:
      radial-gradient(42rem 32rem at 15% -10%, rgba(var(--brand-soft-rgb), 0.12), transparent 60%),
      #0c0e11;
  }

  .batik-medallion {
    position: absolute;
    right: -90px;
    bottom: -90px;
    z-index: -1;
    opacity: 0.4;
    pointer-events: none;
  }

  .card {
    position: relative;
    width: min(30rem, 100%);
    padding: 2.5rem 2.25rem;
    background: #16181b;
    border: 1px solid rgba(255, 255, 255, 0.09);
    border-radius: 1.1rem;
    opacity: 0;
    animation: card-in 0.5s 0.05s ease forwards;
  }

  @keyframes card-in {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .brand {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1.75rem;
  }

  .brand img {
    width: 32px;
    height: 32px;
    object-fit: contain;
  }

  .brand-text span {
    display: block;
    font-size: 14px;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: #f2f4f2;
  }

  .brand-text small {
    display: block;
    margin-top: 0.05rem;
    font-size: 11px;
    font-weight: 500;
    color: #7c8791;
  }

  .heading {
    padding-bottom: 1.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.09);
  }

  .eyebrow {
    margin: 0 0 0.4rem;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--brand-soft);
  }

  .heading h1 {
    margin: 0;
    font-size: 21px;
    font-weight: 800;
    letter-spacing: -0.01em;
    line-height: 1.3;
  }

  .heading p {
    margin: 0.5rem 0 0;
    font-size: 13px;
    line-height: 1.6;
    color: #98a1ac;
  }

  .team {
    list-style: none;
    margin: 0 0 1.75rem;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
  }

  .team li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.07);
  }

  .team li:last-child { border-bottom: 0; }

  .who strong {
    display: block;
    font-size: 13.5px;
    font-weight: 700;
    color: #f2f4f2;
  }

  .who small {
    display: block;
    margin-top: 0.15rem;
    font-size: 11px;
    color: #7c8791;
    font-variant-numeric: tabular-nums;
  }

  .role {
    flex-shrink: 0;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: var(--brand-light);
    background: rgba(var(--brand-soft-rgb), 0.14);
  }

  .closing {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid rgba(255, 255, 255, 0.09);
    font-size: 11px;
    color: #626a72;
  }

  .foot {
    position: absolute;
    bottom: 1.25rem;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 11.5px;
    color: #52585e;
  }

  @media (max-width: 480px) {
    .card { padding: 2rem 1.5rem; }
    .closing { flex-direction: column; gap: 0.3rem; }
  }
</style>
</head>
<body>

<div class="wrap">
  <svg class="batik-medallion" width="280" height="280" viewBox="0 0 280 280" aria-hidden="true">
    <defs>
      <radialGradient id="batikFade" cx="35%" cy="35%" r="70%">
        <stop offset="45%" stop-color="#fff" stop-opacity="1"/>
        <stop offset="100%" stop-color="#fff" stop-opacity="0"/>
      </radialGradient>
      <mask id="batikMask"><rect width="280" height="280" fill="url(#batikFade)"/></mask>
    </defs>
    <g mask="url(#batikMask)" fill="none" style="stroke:var(--brand-soft)" stroke-width="1.1">
      <circle cx="140" cy="140" r="118"/>
      <circle cx="140" cy="140" r="96"/>
      <g transform="translate(140,140)">
        <g stroke-width="1.3">
          <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(0)"/>
          <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(45)"/>
          <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(90)"/>
          <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(135)"/>
          <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(180)"/>
          <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(225)"/>
          <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(270)"/>
          <path d="M0 -86 C 14 -70, 14 -50, 0 -40 C -14 -50, -14 -70, 0 -86 Z" transform="rotate(315)"/>
        </g>
        <circle r="16"/>
        <circle r="8" style="fill:var(--brand-soft)" stroke="none"/>
      </g>
    </g>
  </svg>

  <main class="card">
    <div class="brand">
      <img src="{{ asset('images/logo-unsri.webp') }}" alt="Lambang Universitas Sriwijaya">
      <div class="brand-text">
        <span>SAPTO</span>
        <small>Sistem Akreditasi Perguruan Tinggi Online</small>
      </div>
    </div>

    <div class="heading">
      <p class="eyebrow">Universitas Sriwijaya</p>
      <h1>Tim di balik SAPTO</h1>
      <p>Dicatat dengan bangga, tim yang membangun dan merawat sistem ini:</p>
    </div>

    <ul class="team">
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

    <div class="closing">
      <span>No. Ref: SAPTO/TIM/2026</span>
      <span>Palembang, {{ now()->format('d.m.Y') }}</span>
    </div>
  </main>

  <div class="foot">&copy; {{ date('Y') }} SAPTO &mdash; Universitas Sriwijaya</div>
</div>

</body>
</html>
