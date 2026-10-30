<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Technilium API</title>
    <meta name="description" content="API REST du backend Technilium - marketplace de produits technologiques.">
    <link rel="icon" type="image/png" href="{{ asset('brand/logo.png') }}">
    <style>
        :root {
            --bg: #050505;
            --surface: #121316;
            --line: #26282d;
            --ink: #f3f4f6;
            --ink-soft: #9ca3af;
            --gold: #f5a800;
            --gold-deep: #d87810;
            color-scheme: dark;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background:
                radial-gradient(60rem 30rem at 50% -10%, rgba(245, 168, 0, .16), transparent 70%),
                var(--bg);
            color: var(--ink);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.5;
        }
        header { border-bottom: 3px solid var(--gold); background: #000; }
        .bar, main, footer { width: 100%; max-width: 64rem; margin: 0 auto; padding: 0 1rem; }
        .bar { display: flex; align-items: center; justify-content: space-between; height: 4rem; }
        .brand { display: flex; align-items: center; gap: .6rem; color: var(--gold); font-size: 1.5rem; font-weight: 700; letter-spacing: -.02em; text-decoration: none; }
        .brand img { width: 2.25rem; height: 2.25rem; }
        .status { display: inline-flex; align-items: center; gap: .45rem; font-size: .85rem; color: var(--ink-soft); }
        .status::before { content: ""; width: .55rem; height: .55rem; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, .2); }
        main { flex: 1; padding-top: 4rem; padding-bottom: 3rem; }
        .hero { text-align: center; }
        .hero img { width: 7rem; height: 7rem; margin: 0 auto 1.25rem; border-radius: 1.25rem; box-shadow: 0 0 0 1px var(--line), 0 12px 40px rgba(245, 168, 0, .18); }
        h1 { margin: 0 0 .75rem; font-size: clamp(2rem, 5vw, 3rem); letter-spacing: -.03em; }
        h1 span { color: var(--gold); }
        .hero p { margin: 0 auto; max-width: 38rem; color: var(--ink-soft); font-size: 1.05rem; }
        .actions { display: flex; flex-wrap: wrap; justify-content: center; gap: .75rem; margin-top: 2rem; }
        .btn { display: inline-block; padding: .75rem 1.4rem; border-radius: 3px; font-weight: 600; text-decoration: none; transition: background .15s, border-color .15s; }
        .btn-primary { background: linear-gradient(135deg, var(--gold), var(--gold-deep)); color: #111; }
        .btn-primary:hover { background: var(--gold); }
        .btn-ghost { border: 1px solid var(--line); color: var(--ink); }
        .btn-ghost:hover { border-color: var(--gold); color: var(--gold); }
        .grid { display: grid; gap: 1rem; margin-top: 3.5rem; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }
        .card { padding: 1.25rem; background: var(--surface); border: 1px solid var(--line); border-bottom: 3px solid var(--gold); border-radius: 3px; }
        .card h2 { margin: 0 0 .35rem; font-size: 1rem; color: var(--gold); }
        .card p { margin: 0; font-size: .9rem; color: var(--ink-soft); }
        footer { padding-top: 1.5rem; padding-bottom: 1.5rem; border-top: 1px solid var(--line); font-size: .8rem; color: var(--ink-soft); text-align: center; }
        code { padding: .1rem .35rem; background: #000; border: 1px solid var(--line); border-radius: 3px; color: var(--gold); font-size: .85em; }
    </style>
</head>
<body>
    <header>
        <div class="bar">
            <a class="brand" href="/"><img src="{{ asset('brand/logo.png') }}" alt="">Technilium</a>
            <span class="status">API opérationnelle</span>
        </div>
    </header>

    <main>
        <section class="hero">
            <img src="{{ asset('brand/logo.png') }}" alt="Logo Technilium">
            <h1>API <span>Technilium</span></h1>
            <p>Backend de la marketplace de produits technologiques : catalogue, commandes avec paiement à la livraison, comptes, support et gestion du backoffice.</p>
            <div class="actions">
                <a class="btn btn-primary" href="{{ url('/api/documentation') }}">Documentation Swagger</a>
                <a class="btn btn-ghost" href="{{ url('/api/v1/catalog/products') }}">Explorer le catalogue</a>
            </div>
        </section>

        <section class="grid" aria-label="Aperçu">
            <div class="card"><h2>Catalogue</h2><p>Catégories, produits, variantes et offres des vendeurs.</p></div>
            <div class="card"><h2>Commandes</h2><p>Panier, commande et suivi, paiement à la livraison.</p></div>
            <div class="card"><h2>Authentification</h2><p>Jetons Bearer via Sanctum : <code>Authorization: Bearer …</code></p></div>
            <div class="card"><h2>Version</h2><p>Toutes les routes sont préfixées par <code>/api/v1</code>.</p></div>
        </section>
    </main>

    <footer>&copy; {{ date('Y') }} Technilium - Produits vérifiés, garantie sur chaque appareil.</footer>
</body>
</html>
