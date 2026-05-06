# AmoPoint — тестовое задание PHP-разработчика

Реализация тестового задания на позицию PHP-разработчика. Состав:

1. **Команда `jokes:fetch`** раз в 5 минут забирает шутку с `https://official-joke-api.appspot.com/random_joke` и сохраняет в БД. Endpoint **`GET /api/jokes`** отдаёт записи в JSON.
2. **`testlist.js`** — JS-решение для страницы `https://test.amopoint-dev.ru/testzz/testlist.html`: при смене значения `<select name="type_val">` остаются видимыми только поля, в `name` которых содержится выбранное значение.
3. **Счётчик посещений** (бонус) — JS-коллектор + Laravel-бэкенд + страница статистики с графиками под авторизацией. _В работе._

Подробный план, разбор алгоритмов и отвергнутые альтернативы — в [`docs/test-task/`](docs/test-task/PLAN.md).

---

## Стек

- **Laravel 13.7**, PHP **^8.3**
- **MySQL** для приложения (БД `amo_point`)
- (для бонуса будет добавлена **SQLite** под аналитику посещений)
- **Breeze** (Blade-стек) — авторизация
- Tailwind v4 + Alpine.js, сборка через Vite
- Тесты: PHPUnit (SQLite `:memory:`), стиль: Laravel Pint

---

## Развёртывание

Требования: PHP 8.3+, Composer, Node 24 (см. `.nvmrc`), MySQL 8+.

```bash
git clone git@github.com:mazurok86/amo-point.git
cd amo-point

# 1. БД: создать MySQL-схему
mysql -uroot -e 'CREATE DATABASE amo_point CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'

# 2. .env (правьте DB_* при необходимости)
cp .env.example .env

# 3. Установка + ключ + миграции + сборка фронта
composer setup
```

`composer setup` — agregate-скрипт из `composer.json`, эквивалент:
`composer install` → `cp .env.example .env` → `php artisan key:generate` → `php artisan migrate --force` → `npm install` → `npm run build`.

### Запуск dev-окружения

Один процесс — поднимает сервер, очередь, лог-тейл и Vite параллельно:
```bash
composer dev
```
По умолчанию слушает `http://127.0.0.1:8000`.

Раздельно:
```bash
php artisan serve              # http://127.0.0.1:8000
php artisan schedule:work      # запуск планировщика (для задания 1)
php artisan queue:listen
php artisan pail               # тейл логов
npm run dev                    # Vite HMR
```

### Альтернатива: nginx vhost

В `nginx/servers/` лежит конфиг для `amo-point.local`. Если используете — добавьте `127.0.0.1 amo-point.local` в `/etc/hosts` и проект будет доступен по `http://amo-point.local`.

---

## Проверка ТЗ

### Задание 1 — `jokes:fetch` + `GET /api/jokes`

**Запуск по расписанию** (раз в 5 минут):
```bash
php artisan schedule:work
```
В prod достаточно одной строки в системном `cron`:
```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Зарегистрированный джоб виден через:
```bash
php artisan schedule:list
# → */5 * * * *  php artisan jokes:fetch
```

**Одноразовый запуск** (без ожидания планировщика):
```bash
php artisan jokes:fetch
# → "Saved joke #<id>"
```

**JSON-роут**:
```bash
curl http://127.0.0.1:8000/api/jokes
# Ответ:
# {
#   "data": [{ "id": 1, "external_id": 42, "type": "...", "setup": "...", "punchline": "...", "fetched_at": "..." }, ...],
#   "links": { ... },
#   "meta": { "current_page": 1, "per_page": 50, "total": N }
# }
```
Поддерживается `?per_page=N` (1..100, по умолчанию 50).

Тесты этой части:
```bash
php artisan test --filter='Jokes|FetchJokes'
```

### Задание 2 — `testlist.js`

Деливерабл — единственный файл [`public/testzz/testlist.js`](public/testzz/testlist.js). Работает в обеих формах поставки из ТЗ:

**Способ A — подключаемый файл**
```html
<script src="testlist.js"></script>
```
IIFE, без зависимостей, ничего не выкатывает в `window`. Работает что в `<head>` (через `DOMContentLoaded`), что перед `</body>` (через проверку `document.readyState`).

**Способ B — сниппет в DevTools Console**
1. Открыть `https://test.amopoint-dev.ru/testzz/testlist.html`.
2. F12 → вкладка **Console**.
3. Скопировать **весь** контент `public/testzz/testlist.js`.
4. Вставить, Enter.

После вставки скрипт стартует немедленно (DOM уже готов), навешивает делегированный listener `change` на `document` и применяет видимость по текущему значению select. Меняйте «Тип» — пересчёт мгновенный.

> Chrome при первой вставке кода в Console может потребовать набрать «allow pasting».

**Локальное зеркало** для проверки без выхода на внешний хост: `public/testzz/testlist.html` (откройте по `http://amo-point.local/testzz/testlist.html` или скопируйте файл куда удобно). Зеркало повторяет реальную разметку и **не часть деливерабла**.

Алгоритм и список отвергнутых альтернатив — в [`docs/test-task/02-dynamic-fields.md`](docs/test-task/02-dynamic-fields.md).

### Задание 3 — счётчик посещений

_Будет добавлено после реализации._ Проектное описание: [`docs/test-task/03-visit-counter.md`](docs/test-task/03-visit-counter.md).

---

## Тесты, стиль

```bash
php artisan test                       # вся сьюита (SQLite :memory:, не трогает MySQL)
./vendor/bin/pint --test               # проверка стиля
./vendor/bin/pint                      # автофикс стиля
```

---

## Структура

| Путь | Что |
|------|-----|
| `app/Console/Commands/FetchJokes.php` | Команда `jokes:fetch` |
| `app/Models/Joke.php` | Модель шутки |
| `app/Http/Controllers/Api/JokeController.php` | Контроллер `/api/jokes` |
| `app/Http/Resources/JokeResource.php` | JSON-ресурс |
| `routes/api.php` | API-роуты |
| `bootstrap/app.php` | Регистрация расписания и роутов |
| `public/testzz/testlist.js` | JS-решение задания 2 |
| `public/testzz/testlist.html` | Локальное зеркало целевой страницы |
| `docs/test-task/` | План реализации, разбор алгоритмов |

Для агентов и доп. контекста по проекту см. [`CLAUDE.md`](CLAUDE.md).
