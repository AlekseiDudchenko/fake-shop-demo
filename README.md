# Spaß-Onlineshop (Demo für Video)

Eine statische HTML-Seite „Produktkarte" — für die Aufnahme eines lustigen Instagram-Videos.
Keine Logik, kein Backend — nur eine schöne Seite, die man im Browser öffnen und aufnehmen kann.

## So passt du sie an

1. **Produktfoto** — Datei neben `index.html` ablegen und `product.jpg` nennen
   (anderer Name/Format? Dann `src="product.jpg"` im `<img>`-Tag in `index.html` anpassen).
2. **Produktname** — Text `Coole Kaffeetasse` ersetzen (Tag `<h1 class="title">`).
3. **Preis** — `14,99 €` (alt, durchgestrichen) und `6,99 €` (neu) im `.price-row`-Block ersetzen.
4. **Beschreibung** — Text innerhalb von `<p class="desc">` ersetzen.

## Öffnen

Einfach doppelt auf `index.html` klicken — öffnet sich im Browser. Kein Build/Server nötig.

## Veröffentlichen (optional)

Für einen Link für Stories: GitHub Pages in den Repo-Einstellungen aktivieren
(Settings → Pages → Source: `main` / root). Die Seite ist dann erreichbar unter
`https://<username>.github.io/fake-shop-demo/`.
