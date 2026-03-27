# Wasserkarte

Interaktive Datenkarte der Wassermeisterei zur Visualisierung von Bodenfeuchte-, Boden- und Standortdaten. Das Projekt besteht aus einem Vue-Frontend und einer PHP-API, die Daten aus ThingsBoard lädt, aufbereitet und cached.

## Über das Projekt

Die Wassermeisterei ist ein Citizen-Science-Projekt im Hohen Flaeming. In der trockensten Region Deutschlands stellen Buerger*innen Bodenfeuchte-Sensoren auf und sammeln Daten, um Boeden besser zu verstehen und Strategien fuer eine duerre-resiliente Landschaft zu entwickeln.

Die Wasserkarte macht diese Daten oeffentlich nutzbar. Sie zeigt Sensorstandorte, Bodenfeuchte in verschiedenen Tiefen, historische Zeitreihen sowie zusaetzliche Standort- und Bodendaten.

Die Umsetzung erfolgt in Zusammenarbeit zwischen dem Verein Lebendiger Lernort Arensnest und dem Smart-City-Modellprojekt der Stadt Bad Belzig, der Zukunftsschusterei.

Projektleitung Wassermeisterei: Daniel Diehl
Projektleitung Zukunftsschusterei: Malte Specht
Design und Programmierung Wasserkarte: Nikolaus Baumgarten

## Stack

- Vue 3, Vue Router, Vite, OpenLayers, D3
- ThingsBoard als Datenquelle
- PHP-API Cache

## Voraussetzungen

- Node.js 18 oder neuer
- npm
- PHP 8.0 oder neuer
- PHP-Erweiterungen `curl` und `zlib`
- lokaler PHP-Webserver unter `http://localhost/wasserkarte/api/`

Der Vite-Proxy in [`vite.config.js`](./vite.config.js) leitet `/api` standardmäßig an `http://localhost/wasserkarte/api/` weiter.

## Installation

### 1. Abhängigkeiten installieren

```bash
npm install
```

### 2. PHP-Konfiguration anlegen

```bash
cp api/config-sample.php api/config.php
mkdir -p api/cache
```

In `api/config.php` mindestens anpassen:

- `THINGSBOARD_URL`
- `USERNAME`
- `PASSWORD`
- `REFRESH_SECRET`

Wichtig: `api/cache/` muss für PHP beschreibbar sein.

### 3. Lokale API bereitstellen

Das Projekt erwartet lokal diese Struktur:

```text
http://localhost/wasserkarte/
http://localhost/wasserkarte/api/
```

Wenn dein Setup anders ist, passe den Proxy in `vite.config.js` an.

### 4. Erste Cache-Dateien erzeugen

Das Frontend lädt beim Start direkt vorbereitete Cache-Dateien. Ohne diese Dateien erscheint die Karte nicht sinnvoll befüllt.

```bash
php api/lasttelemetry.php
php api/dailyaverages.php
```

## Entwicklung

```bash
npm start
```

## Build und Deployment

```bash
npm run build
npm run serve
npm run upload
npm run dist
```

Für den FTP-Upload wird eine `.env` im Projektverzeichnis erwartet:

```env
FTP_HOST=...
FTP_USER=...
FTP_PASSWORD=...
FTP_REMOTE_DIR=...
```

Der Build schreibt nach `dist/` und kopiert `api/` nach `dist/api/`. Das Verzeichnis `api/cache/` wird beim Upload bewusst ausgelassen.

## Wichtige Cronjobs

Die Cronjobs sind für den produktiven Betrieb notwendig. Ohne sie werden die Cache-Dateien nicht aktuell erzeugt, und die Anwendung lädt unvollständig oder veraltet.

```cron
0 */2 * * * /usr/bin/php /pfad/zum/projekt/api/lasttelemetry.php >> $HOME/wasserkarte.log 2>&1
5 0 * * * /usr/bin/php /pfad/zum/projekt/api/dailyaverages.php >> $HOME/wasserkarte.log
```

- `api/lasttelemetry.php` aktualisiert Gerätedaten und letzte Messwerte, sollte mindestens alle zwei Stunden ausgeführt werden
- `api/dailyaverages.php` erzeugt tägliche aggregierte Zeitreihen und die komprimierten Gesamt-Caches. Sollte kurz nach Mitternacht ausgeführt werden.

Die Skripte können auch manuell ausgeführt werden, etwa nach einem frischen Setup oder Deploy.

## API

```text
GET /api/
GET /api/?deviceId=<ID>&time=<all|day|week|month|year>&agg=<1d|raw>
GET /api/?refresh=1&secret=<REFRESH_SECRET>
```

## Lizenz

Der Quellcode dieses Repositories steht unter der Apache License 2.0. Details siehe [`LICENSE`](./LICENSE).

Lizenzinformationen zu verwendeten Bibliotheken liegen unter [`public/lizenzen/lizenzen.txt`](./public/lizenzen/lizenzen.txt).

Hinweis: Die Apache-Lizenz gewaehrt keine Markenrechte. Namen, Logos, Foerderkennzeichen und sonstige geschuetzte Kennzeichen sollten vor einer oeffentlichen Veroeffentlichung separat geprueft werden.
