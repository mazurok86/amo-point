# Этап 3 (бонус) — Счётчик посещений

## Архитектура

Два компонента:
1. **JS-коллектор** — подключается тегом `<script src="/track.js" async>` к произвольному сайту.
2. **Бэкенд** — приём, обогащение (geo + UA), хранение, страница статистики под Breeze auth.

## Хранилище

Отдельное соединение **`sqlite`** в `config/database.php` (`database/visits.sqlite`). Основная MySQL не трогается. Удовлетворяет ТЗ («sqlite или другой»).

В тестах — то же `:memory:` с принудительным connection в трейте/setUp.

## Файлы

```
public/track.js                                       # клиентский коллектор
app/Models/Visit.php
app/Http/Controllers/Api/VisitController.php          # приём
app/Http/Controllers/StatsController.php              # страница статистики
app/Http/Requests/StoreVisitRequest.php
app/Services/Geo/GeoResolver.php                      # IP -> {country, city}
app/Services/Geo/IpApiResolver.php                    # реализация (ip-api.com)
app/Services/UserAgentParser.php                      # обёртка над jenssegers/agent
database/migrations/..._create_visits_table.php       # отдельное соединение sqlite
resources/views/stats/index.blade.php                 # графики Chart.js
routes/web.php                                        # /stats (auth)
routes/api.php                                        # POST /api/visits (без auth, throttle)
tests/Feature/Visits/StoreVisitTest.php
tests/Feature/Visits/StatsPageTest.php
```

Зависимости (composer):
- `jenssegers/agent` — UA parser.
- (geo) на dev — простой HTTP-клиент к `ip-api.com` (без зависимостей). На prod при желании — `torann/geoip` + MaxMind GeoLite2.

JS:
- Chart.js — через CDN на странице статистики (без npm-зависимости, чтобы не раздувать сборку Vite). Альтернатива — поставить через `npm i chart.js` и собрать в bundle. Решим в момент реализации.

## Миграция `visits` (на sqlite-соединении)

| поле | тип | заметка |
|------|-----|---------|
| id | bigIncrements | |
| visitor_uid | string(36), index | UUID v4, генерится клиентом, хранится в localStorage |
| ip | string(45) | поддержка IPv6 |
| country | string(2), nullable | ISO-код |
| city | string(120), nullable | |
| device | string(20) | desktop / tablet / mobile / bot |
| browser | string(50), nullable | |
| os | string(50), nullable | |
| page_url | text | |
| referrer | text, nullable | |
| created_at | timestamp, index | для часовой группировки |

`updated_at` не нужен (события неизменяемы) — миграция: `$table->timestamp('created_at')->index()`, без `timestamps()`.

## JS-коллектор `public/track.js`

Собирает на клиенте:
- `page_url`, `referrer`, `title`
- `screen` (`${screen.width}x${screen.height}`)
- `tz` (`Intl.DateTimeFormat().resolvedOptions().timeZone`)
- `visitor_uid` — UUID v4 в `localStorage` (создаётся при первом заходе)
- `userAgent` (для парсинга на бэке)

Отправка через `navigator.sendBeacon('/api/visits', blob)` (не блокирует unload). Fallback — `fetch(..., {keepalive: true})`.

Что **не** собираем на клиенте:
- **IP** — берём на сервере из `$request->ip()` (учесть `TrustProxies` если за nginx).
- **city/country** — сервер по IP через geo-резолвер.

## Backend

### POST `/api/visits`
- `routes/api.php`, без CSRF, throttle `throttle:60,1` per IP.
- В `bootstrap/app.php` добавить `validateCsrfTokens(except: ['/api/visits'])` на всякий случай.
- CORS — `config/cors.php` (создать через `php artisan config:publish cors` если нужно), пути `api/visits`.
- `StoreVisitRequest`: `visitor_uid` (uuid), `page_url` (url, max 2048), `referrer` (nullable, url, max 2048), `user_agent` (string, max 512).
- В контроллере: парсим UA → device/browser/os, резолвим geo → country/city, пишем в БД. Geo и UA — синхронно, но с тайм-аутом 500 мс. Если geo-резолвер падает — пишем без city (страница статистики переживёт).

### GET `/stats` (auth middleware)
Blade-страница с двумя canvas:
- Bar chart: уникальные визиты по часам за выбранный день. Запрос:
  ```sql
  SELECT strftime('%H', created_at) AS h, COUNT(DISTINCT visitor_uid) AS c
  FROM visits WHERE date(created_at) = ? GROUP BY h ORDER BY h
  ```
- Pie chart: топ-N городов за тот же период. `LIMIT 10`, остальное склеить в `Other`.
- Простой `<input type="date">` + form GET (без JS-фреймворка). Данные кладём прямо в `data-*` атрибуты canvas → Chart.js читает из dataset.

## Уникальность визита

`DISTINCT visitor_uid` в пределах часа. Если localStorage недоступен (приватный режим) — JS генерит per-page UUID; на сервере fallback ключ — `md5(ip + ua + date(H))`. Зафиксировать как осознанный компромисс в README.

## Тесты

- `StoreVisitTest`:
  - `Http::fake([...])` для geo-резолвера → POST с валидным payload → запись в БД с распарсенными city/device.
  - Невалидный UUID → 422.
  - Throttle: 61-й запрос в минуту → 429.
- `StatsPageTest`:
  - Гость → 302 на `/login`.
  - Авторизованный пользователь → 200, страница содержит canvas’ы.
  - Сидируем визиты → JSON-эндпоинт (если выделим) или dataset в HTML соответствует ожидаемой агрегации.

## Альтернативы (для README)

| Альтернатива | Почему не выбрана |
|---|---|
| Очередь `dispatch(new RecordVisit(...))` | Прирост сложности (queue worker, миграция jobs на sqlite-соединение). Объём ~1 запрос на визит — синхронно нормально. |
| Хранить в той же MySQL | Возможно, но ТЗ упоминает sqlite. Изоляция аналитики облегчает экспорт/архивацию. |
| ApexCharts / ECharts / D3 | Chart.js — наименьший по весу при достаточной функциональности (bar + pie). |
| Парсинг UA на клиенте через `navigator.userAgentData` (Client Hints) | Не во всех браузерах, не для Safari. Серверный парсер надёжнее. |
| MaxMind GeoLite2 локально с самого начала | Требует регистрации, скачивания базы. Для тестового достаточно `ip-api.com` (free tier ~45 req/min) с упоминанием prod-варианта. |
| Tracking pixel `<img src=".../t.gif?...">` вместо JS+sendBeacon | Работает без JS, но невозможно собрать tz/screen/visitor_uid. JS-коллектор универсальнее. |

## Definition of Done

- [ ] Соединение `sqlite` для аналитики настроено в `config/database.php`.
- [ ] Миграция применяется на отдельном соединении.
- [ ] `track.js` подключается через `<script async>`, отправляет данные через `sendBeacon`, fallback работает.
- [ ] `POST /api/visits` принимает данные, обогащает geo+UA, пишет в sqlite.
- [ ] `/stats` под `auth`, отображает bar+pie корректно для тестовых данных.
- [ ] Все тесты зелёные.
- [ ] `./vendor/bin/pint --test` без замечаний.
- [ ] `npm run build` (если Chart.js всё-таки через bundle) — без ошибок.
