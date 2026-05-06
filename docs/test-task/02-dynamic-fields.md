# Задание 2 — JS: видимость полей по «Тип»

Решение НЕ интегрируется в Laravel-приложение — это standalone-файл/сниппет под чужую страницу `https://test.amopoint-dev.ru/testzz/testlist.html` (исходный HTTP отвечает 301 → HTTPS).

## Целевая страница

```html
<select name="type_val">
    <option value="1">1</option> ... <option value="5">5</option>
</select>

<p>Поле N   <input name="input_N" type="text"></p>   (N = 1..7)
<p><input name="button_12" type="button" value="Кнопка 1"></p>
<p><input name="button_28" type="button" value="Кнопка 2"></p>
<p><input name="button_88" type="button" value="Кнопка 4"></p>
<p><input name="button_33" type="button" value="Кнопка 3"></p>
<p><input name="button_1"  type="button" value="Кнопка 8"></p>
```

Ключевое:
- Единственный select — `<select name="type_val">`. Селектор точечный: `select[name="type_val"]`.
- Кнопки `<input type="button" name="button_*">` тоже «поля с name» → участвуют в фильтре. `FIELD_SELECTOR = 'input[name]'`, без отсечения по `type`.
- Каждое поле обёрнуто в `<p>` с инлайн-меткой («Поле N» / «Кнопка N»). Скрываем родителя `field.parentElement` — input и метка уходят вместе.

## Деливерабл

`public/testzz/testlist.js` — IIFE, без зависимостей, ~30 строк. Подключается тегом `<script src="testlist.js">` либо тело IIFE копируется в DevTools Console.

## Алгоритм

1. На `DOMContentLoaded` (или сразу через проверку `document.readyState` — для Console-сценария) находим `select[name="type_val"]`.
2. Слушаем `change` напрямую на нём — страница статичная, делегирование смысла не имеет.
3. На каждое срабатывание: `value = select.value.trim().toLowerCase()`; перебираем `input[name]`; для каждого — `match = field.name.toLowerCase().includes(value)`; ставим `field.parentElement.hidden = !match`.
4. Запускаем один раз сразу после init — на целевой странице select по умолчанию `value="1"`, начальное состояние сразу корректное.
5. Пустое значение → все поля видны (sane default; на целевой странице такого option нет, но сохраняем для устойчивости).

`String.prototype.includes` (substring) — буква ТЗ: «есть значение элемента списка» = подстрока. На целевой странице при `type_val=1` это естественно ложится: видны `input_1`, `button_12`, `button_1`.

## Альтернативы

| Подход | Почему не выбран |
|---|---|
| **CSS-attribute-selector + динамический `<style>`** (`input[name*="x"] { display: revert }`) | Прячет только сам input, не родительскую обёртку с меткой. `:has()` решает, но усложняет селектор. |
| **Универсальный `closest('.form-row, .field, fieldset, p, label, li')` + `label[for=]` через `CSS.escape`** | Покрывает разные варианты разметки (вложенный label, отдельный label через `for=`, fieldset и т.д.), но это спекуляция под кейсы вне ТЗ. На целевой странице обёртка всегда `<p>` = `parentElement`. |
| **Расширенный `FIELD_SELECTOR`** (`input + textarea + select` с исключениями) | На целевой странице есть только `<input>`. С простым `input[name]` source-`<select>` сам не попадает в выборку — отдельная проверка `field === sourceSelect` не нужна. |
| **Делегирование `change` на `document` с `e.target.matches(...)`** | Полезно при динамическом DOM. Целевая страница статичная — прямой listener короче. |
| **Гибкий селектор `select[name*="type" i]`** (case-insensitive substring) | Защищает от переименования, но это защита от ситуации, не описанной в ТЗ. На реальной странице select ровно один и точно с этим именем. |
| **MutationObserver** | Нужен только при динамическом добавлении полей. Оверкилл. |
| **jQuery** `$('input[name*=...]').show()` | ~30 КБ ради десятка строк нативного JS. На странице есть jQuery 1.8.3, но мы её не трогаем. |
| **Alpine / Vue / React** | Требует переписывать разметку. ТЗ просит сниппет к существующей странице. |
| **Жёсткая мапа `{type: [имена полей]}`** | Не масштабируется, противоречит ТЗ («поля, в `name` которых есть значение»). |
| **`style.display='none'` вместо `hidden`-атрибута** | `hidden` семантичен, не конфликтует с inline-стилями страницы. |
| **Анти-FOUC через инжект `<style>` с `:has()` + удаление после `init()`** | ТЗ не требует. В Console-сценарии (основной для проверяющего) проблема не возникает в принципе. ~40 строк ради граничного случая размывают ядро алгоритма. |
