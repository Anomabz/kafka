<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#050a12">
    <meta name="robots" content="noindex, nofollow">
    <title>¯\_(ツ)_/¯ — SAPTO</title>
    <link rel="icon" href="{{ asset('images/logo-unsri.webp') }}" type="image/webp">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,600,700,800" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #050a12;
            color: #f3faf8;
        }

        body {
            position: relative;
            display: grid;
            place-items: center;
            padding: 2rem 1.25rem;
            overflow-x: hidden;
        }

        /* Cursor-follow spotlight, set via JS on mousemove */
        .spotlight {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background: radial-gradient(600px circle at var(--x, 50%) var(--y, 30%), rgba(129, 140, 248, 0.14), transparent 60%);
            transition: background 0.05s linear;
        }

        /* Drifting aurora blobs */
        .aurora {
            position: fixed;
            inset: -10%;
            z-index: -1;
            filter: blur(70px);
            opacity: 0.75;
        }

        .aurora span {
            position: absolute;
            border-radius: 50%;
            mix-blend-mode: screen;
        }

        .aurora span:nth-child(1) {
            width: 32rem; height: 32rem;
            top: -8%; left: -8%;
            background: radial-gradient(circle, #4338ca, transparent 65%);
            animation: drift1 22s ease-in-out infinite;
        }

        .aurora span:nth-child(2) {
            width: 28rem; height: 28rem;
            bottom: -10%; right: -6%;
            background: radial-gradient(circle, #a5b4fc, transparent 65%);
            animation: drift2 26s ease-in-out infinite;
        }

        .aurora span:nth-child(3) {
            width: 24rem; height: 24rem;
            top: 40%; left: 55%;
            background: radial-gradient(circle, #312e81, transparent 65%);
            animation: drift3 30s ease-in-out infinite;
        }

        @keyframes drift1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(6%, 8%) scale(1.15); }
        }

        @keyframes drift2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-8%, -6%) scale(1.1); }
        }

        @keyframes drift3 {
            0%, 100% { transform: translate(-50%, -50%) scale(1); }
            50% { transform: translate(-55%, -42%) scale(1.2); }
        }

        .noise {
            position: fixed;
            inset: 0;
            z-index: -1;
            background-image: radial-gradient(rgba(255, 255, 255, 0.14) 1px, transparent 1px);
            background-size: 26px 26px;
            mask-image: radial-gradient(52rem 42rem at 50% 0%, black, transparent 75%);
            -webkit-mask-image: radial-gradient(52rem 42rem at 50% 0%, black, transparent 75%);
        }

        .stage {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 46rem;
            text-align: center;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            border: 1px solid rgba(129, 140, 248, 0.4);
            background: rgba(129, 140, 248, 0.1);
            color: #a5b4fc;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            opacity: 0;
            animation: rise 0.6s 0.05s ease forwards;
        }

        .badge .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #a5b4fc;
            box-shadow: 0 0 0 0 rgba(129, 140, 248, 0.6);
            animation: pulse-dot 1.8s ease-in-out infinite;
        }

        @keyframes pulse-dot {
            0% { box-shadow: 0 0 0 0 rgba(129, 140, 248, 0.55); }
            70% { box-shadow: 0 0 0 8px rgba(129, 140, 248, 0); }
            100% { box-shadow: 0 0 0 0 rgba(129, 140, 248, 0); }
        }

        h1 {
            margin: 1.1rem 0 0.6rem;
            font-size: clamp(2.8rem, 9vw, 5rem);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1;
            cursor: default;
            user-select: none;
            background: linear-gradient(110deg, #ffffff 10%, #a5b4fc 45%, #4338ca 70%, #ffffff 100%);
            background-size: 260% auto;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            opacity: 0;
            animation: rise 0.7s 0.15s ease forwards, shimmer 6s linear infinite;
        }

        @keyframes shimmer {
            to { background-position: -260% center; }
        }

        .tagline {
            margin: 0 0 3rem;
            color: rgba(243, 250, 248, 0.68);
            font-size: 15.5px;
            opacity: 0;
            animation: rise 0.7s 0.25s ease forwards;
        }

        .crew {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
            gap: 1rem;
            text-align: left;
        }

        .card {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.9rem;
            padding: 1.15rem 1.25rem;
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.13);
            background: linear-gradient(160deg, rgba(255, 255, 255, 0.09), rgba(255, 255, 255, 0.03));
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            overflow: hidden;
            opacity: 0;
            transform: translateY(16px);
            animation: rise 0.6s ease forwards;
            transition: border-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
        }

        .card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(120px circle at var(--mx, 50%) var(--my, 50%), rgba(129, 140, 248, 0.22), transparent 70%);
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .card:hover {
            border-color: rgba(129, 140, 248, 0.55);
            transform: translateY(-4px);
            box-shadow: 0 14px 32px -18px rgba(67, 56, 202, 0.55);
        }

        .card:hover::before {
            opacity: 1;
        }

        .card:nth-child(1) { animation-delay: 0.35s; }
        .card:nth-child(2) { animation-delay: 0.44s; }
        .card:nth-child(3) { animation-delay: 0.53s; }
        .card:nth-child(4) { animation-delay: 0.62s; }

        .avatar {
            position: relative;
            z-index: 1;
            flex: none;
            width: 2.9rem;
            height: 2.9rem;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 14px;
            color: #06120f;
            background: linear-gradient(135deg, var(--c1), var(--c2));
        }

        .card .meta {
            position: relative;
            z-index: 1;
            min-width: 0;
        }

        .card .no {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: #a5b4fc;
        }

        .card .name {
            margin-top: 0.15rem;
            font-size: 1rem;
            font-weight: 700;
            color: #f8fdfc;
            line-height: 1.25;
        }

        footer {
            margin-top: 2.75rem;
            font-size: 12.5px;
            color: rgba(243, 250, 248, 0.45);
            opacity: 0;
            animation: rise 0.7s 0.85s ease forwards;
        }

        footer .heart {
            display: inline-block;
            color: #a5b4fc;
            animation: beat 1.4s ease-in-out infinite;
        }

        @keyframes beat {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.25); }
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (prefers-reduced-motion: reduce) {
            .badge, h1, .tagline, .card, footer { animation: none !important; opacity: 1; transform: none; }
            .aurora span, footer .heart { animation: none !important; }
        }
    </style>
</head>
<body>
    <div class="aurora"><span></span><span></span><span></span></div>
    <div class="noise"></div>
    <div class="spotlight"></div>

    <div class="stage">
        <span class="badge"><span class="dot"></span>You found it</span>
        <h1>SAPTO</h1>
        <p class="tagline">Dibangun oleh tim kecil yang sering begadang demi angka-angka BAN-PT ini benar.</p>

        <div class="crew">
            @foreach ([
                ['name' => 'Abdul Karim', 'c1' => '#a5b4fc', 'c2' => '#4338ca'],
                ['name' => 'Bemby Dwigantara Anom', 'c1' => '#c4b5fd', 'c2' => '#6d28d9'],
                ['name' => 'Haziratul Qudsi', 'c1' => '#93c5fd', 'c2' => '#2563eb'],
                ['name' => 'Arya Kusuma', 'c1' => '#c7d2fe', 'c2' => '#312e81'],
            ] as $i => $person)
                <div class="card" style="--c1: {{ $person['c1'] }}; --c2: {{ $person['c2'] }}">
                    <div class="avatar">{{ collect(explode(' ', $person['name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</div>
                    <div class="meta">
                        <div class="no">{{ sprintf('%02d', $i + 1) }}</div>
                        <div class="name">{{ $person['name'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <footer>Made with <span class="heart">&#9679;</span> at SAPTO &middot; Universitas Sriwijaya</footer>
    </div>

    <script>
        document.addEventListener('mousemove', function (e) {
            document.documentElement.style.setProperty('--x', e.clientX + 'px');
            document.documentElement.style.setProperty('--y', e.clientY + 'px');
        });

        document.querySelectorAll('.card').forEach(function (card) {
            card.addEventListener('mousemove', function (e) {
                var r = card.getBoundingClientRect();
                card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
                card.style.setProperty('--my', (e.clientY - r.top) + 'px');
            });
        });
    </script>
</body>
</html>
