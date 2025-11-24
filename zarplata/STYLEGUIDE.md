# Styleguide: Tutoring Center CRM — Salary Page

## Обзор дизайн-системы

Тёмная профессиональная тема с тиловым (teal) акцентным цветом. Минималистичный подход с акцентом на читаемость данных и удобство работы с финансовой информацией.

---

## 1. Цветовая палитра

### Фоны
```css
--bg-dark: #0c0f14;        /* Основной фон страницы */
--bg-card: #14181f;        /* Фон карточек и секций */
--bg-elevated: #1a1f28;    /* Приподнятые элементы (таблицы, формы) */
--bg-hover: #1f2631;       /* Состояние при наведении */
```

### Текст
```css
--text-primary: #f0f2f5;   /* Основной текст, заголовки */
--text-secondary: #8b95a5; /* Вторичный текст, описания */
--text-muted: #5a6473;     /* Приглушённый текст, подписи */
```

### Границы
```css
--border: #252b36;         /* Все границы и разделители */
```

### Акцентный цвет (Teal)
```css
--accent: #14b8a6;                        /* Основной акцент */
--accent-dim: rgba(20, 184, 166, 0.15);   /* Фон для активных элементов */
--accent-hover: #0d9488;                  /* При наведении на кнопки */
```

### Статусные цвета
```css
/* Успех / Выплачено */
--status-green: #22c55e;
--status-green-dim: rgba(34, 197, 94, 0.12);

/* Предупреждение / Ожидает */
--status-amber: #f59e0b;
--status-amber-dim: rgba(245, 158, 11, 0.12);

/* Информация / Одобрено */
--status-blue: #3b82f6;
--status-blue-dim: rgba(59, 130, 246, 0.12);

/* Ошибка / Неявка */
--status-rose: #f43f5e;
--status-rose-dim: rgba(244, 63, 94, 0.12);
```

### Типы уроков
```css
/* Индивидуальное занятие */
--lesson-individual: #06b6d4;
--lesson-individual-dim: rgba(6, 182, 212, 0.12);

/* Групповое занятие */
--lesson-group: #a855f7;
--lesson-group-dim: rgba(168, 85, 247, 0.12);
```

---

## 2. Типографика

### Шрифты
```css
/* Основной шрифт для UI */
font-family: 'Nunito', sans-serif;

/* Моноширинный для цифр и сумм */
font-family: 'JetBrains Mono', monospace;
```

### Google Fonts подключение
```html
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
```

### Размеры текста
```css
/* Заголовок страницы */
.page-title {
    font-size: 26px;
    font-weight: 700;
    letter-spacing: -0.02em;
}

/* Заголовок секции (месяц) */
.section-title {
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.01em;
}

/* Обычный текст */
.body-text {
    font-size: 14px;
    font-weight: 400;
    line-height: 1.5;
}

/* Мелкий текст / подписи */
.caption {
    font-size: 12px;
    color: var(--text-secondary);
}

/* Лейблы в таблицах */
.table-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
}

/* Денежные суммы */
.money {
    font-family: 'JetBrains Mono', monospace;
    font-weight: 600;
}

.money-large { font-size: 26px; }
.money-medium { font-size: 18px; }
.money-small { font-size: 14px; }
```

---

## 3. Компоненты

### 3.1 Кнопки

```css
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    border: none;
    font-family: inherit;
}

/* Основная кнопка */
.btn-primary {
    background: var(--accent);
    color: white;
}
.btn-primary:hover {
    background: var(--accent-hover);
}

/* Вторичная кнопка */
.btn-secondary {
    background: var(--bg-elevated);
    color: var(--text-primary);
    border: 1px solid var(--border);
}
.btn-secondary:hover {
    background: var(--bg-hover);
}

/* Контурная кнопка */
.btn-outline {
    background: transparent;
    color: var(--accent);
    border: 1px solid var(--accent);
}
.btn-outline:hover {
    background: var(--accent-dim);
}
```

### 3.2 Карточки

```css
.card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px;
}

.card-elevated {
    background: var(--bg-elevated);
    border-radius: 10px;
}
```

### 3.3 Навигация (Sidebar)

```css
.sidebar {
    width: 220px;
    background: var(--bg-card);
    border-right: 1px solid var(--border);
    padding: 24px 0;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}

.nav-item:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.nav-item.active {
    background: var(--accent-dim);
    color: var(--accent);
}

.nav-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    padding: 0 8px;
    margin-bottom: 8px;
}
```

### 3.4 Фильтры (Pills/Chips)

```css
.filter-btn {
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    background: var(--bg-elevated);
    color: var(--text-secondary);
    border: 1px solid var(--border);
}

.filter-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.filter-btn.active {
    background: var(--accent-dim);
    color: var(--accent);
    border-color: var(--accent);
}
```

### 3.5 Карточки недель

```css
.week-card {
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px;
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: center;
}

.week-card:hover {
    background: var(--bg-hover);
    border-color: var(--text-muted);
}

.week-card.active {
    background: var(--accent-dim);
    border-color: var(--accent);
}

.week-card.active .week-dates,
.week-card.active .week-amount {
    color: var(--accent);
}
```

### 3.6 Прогресс-бары

```css
/* Горизонтальный прогресс */
.progress-bar {
    height: 6px;
    background: var(--bg-dark);
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 3px;
    background: linear-gradient(90deg, var(--status-green), #4ade80);
    transition: width 0.4s ease;
}

/* Мини прогресс (в карточках недель) */
.week-progress {
    height: 3px;
    background: var(--bg-dark);
    border-radius: 2px;
    margin-top: 8px;
}
```

### 3.7 Бейджи статусов

```css
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 600;
}

.status-pending {
    background: var(--status-amber-dim);
    color: var(--status-amber);
}

.status-approved {
    background: var(--status-blue-dim);
    color: var(--status-blue);
}

.status-paid {
    background: var(--status-green-dim);
    color: var(--status-green);
}
```

### 3.8 Бейджи типов уроков

```css
.lesson-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.badge-individual {
    background: var(--lesson-individual-dim);
    color: var(--lesson-individual);
}

.badge-group {
    background: var(--lesson-group-dim);
    color: var(--lesson-group);
}
```

### 3.9 Кнопка одобрения

```css
.approve-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}

.approve-btn:hover {
    background: var(--status-green-dim);
    border-color: var(--status-green);
    color: var(--status-green);
}

.approve-btn.approved {
    background: var(--status-green);
    border-color: var(--status-green);
    color: white;
    pointer-events: none;
}
```

### 3.10 Аватарки учеников

```css
.student-avatar {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
    color: white;
}

/* Градиенты по предметам */
.avatar-math { 
    background: linear-gradient(135deg, #f59e0b, #f97316); 
}
.avatar-physics { 
    background: linear-gradient(135deg, #3b82f6, #6366f1); 
}
.avatar-informatics { 
    background: linear-gradient(135deg, #10b981, #14b8a6); 
}
```

### 3.11 Тултипы

```css
.tooltip {
    position: absolute;
    bottom: calc(100% + 10px);
    left: 50%;
    transform: translateX(-50%);
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px 16px;
    min-width: 200px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    z-index: 100;
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
}

.parent:hover .tooltip {
    opacity: 1;
    visibility: visible;
}

/* Стрелка тултипа */
.tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 6px solid transparent;
    border-top-color: var(--border);
}
```

---

## 4. Таблицы данных

### Структура таблицы

```css
.table-header {
    display: grid;
    grid-template-columns: 100px 1fr 120px 80px 80px 100px 50px;
    gap: 12px;
    padding: 12px 16px;
    background: var(--bg-dark);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
}

.table-row {
    display: grid;
    grid-template-columns: 100px 1fr 120px 80px 80px 100px 50px;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    align-items: center;
}

.table-row:hover {
    background: var(--bg-hover);
}
```

### Раскрывающиеся строки

```css
.expandable-row {
    cursor: pointer;
}

.expand-icon {
    width: 16px;
    height: 16px;
    color: var(--text-muted);
    transition: transform 0.2s ease;
}

.expandable-row.expanded .expand-icon {
    transform: rotate(180deg);
}

.nested-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    background: var(--bg-dark);
}

.nested-content.expanded {
    max-height: 500px;
}

.nested-row {
    padding-left: 40px; /* Отступ для вложенных строк */
    border-top: 1px solid var(--border);
}
```

---

## 5. Блоки статистики

### Вариант "Минимальный" (рекомендуется)

```css
.stats-minimal {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px 0;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
}

.stats-item {
    padding: 0 40px;
    text-align: center;
    border-right: 1px solid var(--border);
}

.stats-item:last-child {
    border-right: none;
}

.stats-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 26px;
    font-weight: 600;
    margin-bottom: 4px;
}

.stats-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

/* Цвета значений по статусам */
.stats-item.pending .stats-value { color: var(--status-amber); }
.stats-item.approved .stats-value { color: var(--status-blue); }
.stats-item.paid .stats-value { color: var(--status-green); }
.stats-item.total .stats-value { color: var(--text-primary); }
```

### Вариант "Стекированный прогресс"

```css
.stats-stacked {
    padding: 20px 24px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
}

.stacked-bar {
    height: 28px;
    background: var(--bg-dark);
    border-radius: 6px;
    overflow: hidden;
    display: flex;
}

.stacked-segment {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    font-weight: 600;
    color: white;
}

.segment-paid {
    background: linear-gradient(90deg, #16a34a, var(--status-green));
}

.segment-approved {
    background: linear-gradient(90deg, #2563eb, var(--status-blue));
}

.segment-pending {
    background: linear-gradient(90deg, #d97706, var(--status-amber));
}
```

---

## 6. Отступы и размеры

### Spacing scale
```css
--spacing-xs: 4px;
--spacing-sm: 8px;
--spacing-md: 12px;
--spacing-lg: 16px;
--spacing-xl: 24px;
--spacing-2xl: 32px;
--spacing-3xl: 48px;
```

### Border radius
```css
--radius-sm: 4px;    /* Бейджи, мелкие элементы */
--radius-md: 6px;    /* Кнопки, небольшие карточки */
--radius-lg: 8px;    /* Навигация, фильтры */
--radius-xl: 10px;   /* Карточки недель, тултипы */
--radius-2xl: 12px;  /* Блоки статистики */
--radius-3xl: 14px;  /* Секции месяцев */
--radius-full: 20px; /* Pills, округлые фильтры */
```

### Layout
```css
--sidebar-width: 220px;
--main-padding: 28px 32px;
--card-padding: 24px;
--table-row-padding: 14px 16px;
```

---

## 7. Анимации

### Transitions
```css
/* Стандартный переход */
transition: all 0.15s ease;

/* Для раскрытия контента */
transition: max-height 0.3s ease;

/* Для прогресс-баров */
transition: width 0.4s ease;

/* Для иконок (поворот) */
transition: transform 0.2s ease;
```

### Hover эффекты
- Фон меняется на `--bg-hover`
- Цвет текста становится ярче (`--text-primary`)
- Границы подсвечиваются

---

## 8. Иконки

Используются inline SVG с следующими параметрами:
```html
<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="..."/>
</svg>
```

### Размеры иконок
- Навигация: 18x18
- Кнопки: 16x16
- Внутри таблиц: 14x14
- Мелкие элементы: 12x12

---

## 9. Применение в Django-шаблонах

### Подключение стилей
```html
{% load static %}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{% static 'css/salary.css' %}">
```

### CSS-переменные
Все переменные должны быть определены в `:root` в начале CSS файла.

### Классы для Django-шаблонов
```html
<!-- Статус урока -->
<span class="status-badge status-{{ lesson.status }}">{{ lesson.get_status_display }}</span>

<!-- Тип урока -->
<span class="lesson-type-badge badge-{{ lesson.type }}">{{ lesson.type_short }}</span>

<!-- Сумма -->
<span class="money money-medium">{{ lesson.amount|intcomma }}₽</span>

<!-- Кнопка одобрения -->
<button class="approve-btn {% if lesson.is_approved %}approved{% endif %}">...</button>
```

---

## 10. Чеклист для разработчика

- [ ] Подключить Google Fonts (Nunito + JetBrains Mono)
- [ ] Определить все CSS-переменные в `:root`
- [ ] Использовать `--bg-card` для карточек, `--bg-elevated` для вложенных элементов
- [ ] Все денежные суммы — шрифт JetBrains Mono
- [ ] Активные элементы — фон `--accent-dim`, цвет `--accent`
- [ ] Статусные бейджи — соответствующие *-dim фоны
- [ ] Границы везде `1px solid var(--border)`
- [ ] Border-radius по шкале (см. раздел 6)
- [ ] Transition `0.15s ease` для интерактивных элементов
- [ ] Таблицы — CSS Grid с фиксированными колонками
