# Wasserkarte

Interaktive Datenkarte der Wassermeisterei zur Visualisierung von Bodenfeuchte-, Boden- und Standortdaten. Das Projekt besteht aus einem Vue-Frontend und einem PHP API-Cache, der Daten aus ThingsBoard lädt, aufbereitet und cached.

Live-Version: [wasserkarte.org](https://wasserkarte.org)

## Über das Projekt

Die Wassermeisterei ist ein Citizen-Science-Projekt im Hohen Fläming. In der trockensten Region Deutschlands stellen Bürger*innen Bodenfeuchte-Sensoren auf und sammeln Daten, um Böden besser zu verstehen und Strategien fuer eine dürre-resiliente Landschaft zu entwickeln.

Die Umsetzung erfolgt in Zusammenarbeit zwischen dem Verein Lebendiger Lernort Arensnest und dem Smart-City-Modellprojekt der Stadt Bad Belzig [Zukunftsschusterei](https://zukunftsschusterei.de/).

Projektleitung Wassermeisterei: Daniel Diehl  
Projektleitung Zukunftsschusterei: Malte Specht   
Design und Programmierung Wasserkarte: [Nikolaus Baumgarten](https://nikkki.net)  

## Setup

```bash
cp api/config-sample.php api/config.php
mkdir -p api/cache
```

In `api/config.php` mindestens setzen:

- `THINGSBOARD_URL`
- `USERNAME`
- `PASSWORD`
- `REFRESH_SECRET`

`api/cache/` muss fuer PHP beschreibbar sein.

Die API nutzt `curl` fuer Requests an ThingsBoard und `zlib` fuer die komprimierten Cache-Dateien.

## Lokale Entwicklung

Fuer die lokale Entwicklung werden benoetigt:

- PHP 8+
- Webserver mit PHP unter `http://localhost/wasserkarte/api/`
- Node.js 18+
- npm

```bash
npm install
```

Der Dev-Server erwartet lokal diese Struktur:

```text
http://localhost/wasserkarte/
http://localhost/wasserkarte/api/
```

`vite.config.js` proxyt `/api` standardmaessig an `http://localhost/wasserkarte/api/`. Das sollte in der Entwicklung aktiv bleiben, weil Vite PHP-Dateien nicht ausfuehrt.

Vor dem ersten Start die Cache-Dateien erzeugen:

```bash
php api/lasttelemetry.php
php api/dailyaverages.php
```

Dann den Dev-Server starten:

```bash
npm start
```

## Build

```bash
npm run build
npm run serve
```

## Wichtige Cronjobs

Die Cronjobs sind für den produktiven Betrieb notwendig. Ohne sie werden die Cache-Dateien nicht aktuell erzeugt, und die Anwendung lädt unvollständig oder veraltet.

0 */2 * * * /usr/bin/php /pfad/zum/projekt/api/lasttelemetry.php >> $HOME/wasserkarte.log 2>&1
5 0 * * * /usr/bin/php /pfad/zum/projekt/api/dailyaverages.php >> $HOME/wasserkarte.log

    api/lasttelemetry.php aktualisiert Gerätedaten und letzte Messwerte, sollte mindestens alle zwei Stunden ausgeführt werden

    api/dailyaverages.php erzeugt tägliche aggregierte Zeitreihen und die komprimierten Gesamt-Caches. Sollte kurz nach Mitternacht ausgeführt werden.

Die Skripte können auch manuell ausgeführt werden, etwa nach einem frischen Setup oder Deploy.

## Lizenz

Apache License 2.0. Details in [`LICENSE`](./LICENSE).

Lizenzinformationen zu verwendeten Bibliotheken liegen unter public/lizenzen/lizenzen.txt.

Hinweis: Die Apache-Lizenz gewaehrt keine Markenrechte. Namen, Logos, Foerderkennzeichen und sonstige geschuetzte Kennzeichen sollten vor einer oeffentlichen Veroeffentlichung separat geprueft werden.