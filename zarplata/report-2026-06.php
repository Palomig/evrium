<?php
/**
 * Бизнес-отчёт: декабрь 2025 — июнь 2026 (снапшот на 11.06.2026).
 * Только для администраторов. Данные зафиксированы в момент составления;
 * живые цифры — на дашборде (index.php).
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

requireAdmin();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Эвриум · Бизнес-отчёт дек 2025 — июнь 2026</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #0c0f14; --card: #14181f; --elevated: #1a1f28; --border: #252b36;
    --text: #f0f2f5; --text2: #8b95a5; --muted: #5a6473;
    --accent: #14b8a6; --green: #22c55e; --rose: #f43f5e; --amber: #f59e0b;
    --ind: #06b6d4; --grp: #a855f7;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Nunito', sans-serif;
    background: var(--bg); color: var(--text);
    font-size: 15px; line-height: 1.55;
    padding: 40px 16px 80px;
}
.report { max-width: 880px; margin: 0 auto; }
.mono { font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; }

header.rhead { margin-bottom: 36px; }
.rhead .kicker { color: var(--accent); font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; }
.rhead h1 { font-size: 30px; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 8px; }
.rhead .sub { color: var(--text2); font-size: 14px; }
.rhead .method { margin-top: 14px; padding: 12px 16px; background: var(--card); border: 1px solid var(--border); border-left: 3px solid var(--accent); border-radius: 8px; font-size: 13px; color: var(--text2); }

h2 { font-size: 20px; font-weight: 800; margin: 44px 0 16px; letter-spacing: -0.01em; }
h2 .n { color: var(--accent); margin-right: 8px; }

.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 24px 0; }
.kpi { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; }
.kpi .v { font-family: 'JetBrains Mono', monospace; font-size: 24px; font-weight: 700; }
.kpi .l { color: var(--text2); font-size: 12px; margin-top: 2px; }
.kpi.green .v { color: var(--green); } .kpi.purple .v { color: var(--grp); } .kpi.cyan .v { color: var(--ind); } .kpi.amber .v { color: var(--amber); }

table { width: 100%; border-collapse: collapse; background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; font-size: 13.5px; }
th { background: var(--elevated); text-align: right; padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
th:first-child, td:first-child { text-align: left; }
td { padding: 9px 12px; border-top: 1px solid var(--border); text-align: right; }
tr.total td { font-weight: 800; background: var(--elevated); }
.pos { color: var(--green); } .neg { color: var(--rose); }

.chart { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin: 16px 0; }
.chart .ct { font-size: 13px; color: var(--text2); margin-bottom: 14px; font-weight: 700; }
.bars { display: flex; align-items: flex-end; gap: 14px; height: 180px; }
.bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; height: 100%; justify-content: flex-end; }
.bar-stack { width: 100%; max-width: 64px; display: flex; flex-direction: column-reverse; border-radius: 6px 6px 0 0; overflow: hidden; }
.seg-grp { background: linear-gradient(180deg, rgba(168,85,247,0.9), rgba(168,85,247,0.6)); }
.seg-ind { background: linear-gradient(180deg, rgba(6,182,212,0.9), rgba(6,182,212,0.6)); }
.bar-val { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text2); }
.bar-lbl { font-size: 12px; color: var(--muted); }
.legend { display: flex; gap: 16px; margin-top: 12px; font-size: 12px; color: var(--text2); }
.legend span::before { content: ''; display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; }
.legend .lg-grp::before { background: var(--grp); } .legend .lg-ind::before { background: var(--ind); }

.note { color: var(--muted); font-size: 12.5px; margin-top: 8px; }

.findings { display: grid; gap: 12px; }
.finding { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; }
.finding b.t { display: block; margin-bottom: 4px; font-size: 15px; }
.finding .d { color: var(--text2); font-size: 13.5px; }
.finding.warn { border-left: 3px solid var(--amber); }
.finding.crit { border-left: 3px solid var(--rose); }
.finding.opp { border-left: 3px solid var(--green); }

ol.recs { margin-left: 22px; display: grid; gap: 10px; }
ol.recs li { padding-left: 4px; }
ol.recs .why { color: var(--text2); font-size: 13px; }

.disclaimer { margin-top: 40px; padding: 16px 18px; background: var(--card); border: 1px solid var(--border); border-radius: 12px; color: var(--text2); font-size: 13px; }
.backlink { display: inline-block; margin-top: 28px; color: var(--accent); text-decoration: none; font-weight: 700; }
@media print { body { background: #fff; color: #111; } }
</style>
</head>
<body>
<div class="report">

<header class="rhead">
    <div class="kicker">Эвриум · внутренний отчёт</div>
    <h1>Экономика уроков: декабрь 2025 — июнь 2026</h1>
    <div class="sub">Составлен 11.06.2026 · снапшот данных на эту дату · период = вся история учёта (система запущена 15.11.2025, первый урок 01.12.2025)</div>
    <div class="method">
        <b>Методика:</b> только посещаемость из отметок преподавателей × цены и формулы.
        Индивидуальный: 1500 ₽/урок, выплата 900 ₽. Группа: 625 ₽ за пришедшего (5000 ₽/мес ÷ 8 занятий), выплата 900 ₽ + 200 ₽ за каждого пришедшего после 3-го.
        Платёжные таблицы (Gmail-парсинг) не использовались. Май занижен: 39 уроков без отметки.
    </div>
</header>

<!-- Итоги -->
<div class="kpis">
    <div class="kpi"><div class="v mono">969 750 ₽</div><div class="l">выручка за 6 месяцев</div></div>
    <div class="kpi green"><div class="v mono">457 250 ₽</div><div class="l">маржа за 6 месяцев (~76k/мес)</div></div>
    <div class="kpi purple"><div class="v mono">51%</div><div class="l">маржинальность групп</div></div>
    <div class="kpi cyan"><div class="v mono">43%</div><div class="l">маржинальность индивидуальных</div></div>
</div>

<h2><span class="n">1.</span>Маржа по месяцам</h2>

<div class="chart">
    <div class="ct">Маржа, тыс. ₽ (стек: группы + индивидуальные)</div>
    <div class="bars">
        <?php
        // [месяц, маржа группы, маржа индив]
        $months = [
            ['Дек', 39550, 32400], ['Янв', 39000, 33000], ['Фев', 46650, 31800],
            ['Мар', 46125, 39000], ['Апр', 49750, 45900], ['Май*', 19575, 34500],
        ];
        $max = 95650;
        foreach ($months as [$m, $g, $i]):
            $total = $g + $i;
        ?>
        <div class="bar-col">
            <div class="bar-val"><?= number_format(round($total/1000), 0) ?>k</div>
            <div class="bar-stack" style="height: <?= round(100 * $total / $max) ?>%;">
                <div class="seg-grp" style="height: <?= round(100 * $g / $total) ?>%;"></div>
                <div class="seg-ind" style="height: <?= round(100 * $i / $total) ?>%;"></div>
            </div>
            <div class="bar-lbl"><?= $m ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="legend"><span class="lg-grp">Группы</span><span class="lg-ind">Индивидуальные</span></div>
    <div class="note">* Май занижен из-за неотмеченных уроков. Тренд февраль→апрель: +9%/мес.</div>
</div>

<table>
    <thead><tr><th>Месяц</th><th>Группы: выручка</th><th>выплата</th><th>маржа</th><th>Индив: выручка</th><th>выплата</th><th>маржа</th><th>Маржа итого</th></tr></thead>
    <tbody class="mono">
        <tr><td>12.2025</td><td>73 750</td><td>34 200</td><td class="pos">39 550</td><td>81 000</td><td>48 600</td><td class="pos">32 400</td><td><b>71 950</b></td></tr>
        <tr><td>01.2026</td><td>72 500</td><td>33 500</td><td class="pos">39 000</td><td>82 500</td><td>49 500</td><td class="pos">33 000</td><td><b>72 000</b></td></tr>
        <tr><td>02.2026</td><td>86 250</td><td>39 600</td><td class="pos">46 650</td><td>79 500</td><td>47 700</td><td class="pos">31 800</td><td><b>78 450</b></td></tr>
        <tr><td>03.2026</td><td>88 125</td><td>42 000</td><td class="pos">46 125</td><td>97 500</td><td>58 500</td><td class="pos">39 000</td><td><b>85 125</b></td></tr>
        <tr><td>04.2026</td><td>98 750</td><td>49 000</td><td class="pos">49 750</td><td>99 000</td><td>53 100</td><td class="pos">45 900</td><td><b>95 650</b></td></tr>
        <tr><td>05.2026*</td><td>49 375</td><td>29 800</td><td class="pos">19 575</td><td>61 500</td><td>27 000</td><td class="pos">34 500</td><td><b>54 075</b></td></tr>
        <tr class="total"><td>Σ 6 мес</td><td>468 750</td><td>228 100</td><td class="pos">240 650</td><td>501 000</td><td>284 400</td><td class="pos">216 600</td><td>457 250</td></tr>
    </tbody>
</table>

<h2><span class="n">2.</span>Юнит-экономика: группы vs индивидуальные</h2>

<table>
    <thead><tr><th>Метрика</th><th>Группы</th><th>Индивидуальные</th></tr></thead>
    <tbody class="mono">
        <tr><td>Проведено уроков</td><td>221</td><td>317</td></tr>
        <tr><td>Посещений</td><td>750</td><td>334</td></tr>
        <tr><td>Маржа на урок</td><td class="pos"><b>1 089 ₽</b></td><td>683 ₽</td></tr>
        <tr><td>Маржа на посещение</td><td>321 ₽</td><td>648 ₽</td></tr>
        <tr><td>Маржинальность</td><td class="pos"><b>51%</b></td><td>43%</td></tr>
        <tr><td>Средняя явка</td><td>3,4 из 6</td><td>—</td></tr>
    </tbody>
</table>

<div class="chart">
    <div class="ct">Маржа группового урока в зависимости от числа пришедших (₽/урок)</div>
    <table>
        <thead><tr><th>Пришло</th><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th><th>6</th><th>Индив</th></tr></thead>
        <tbody class="mono">
            <tr><td>Маржа</td><td class="neg">−275</td><td>+350</td><td>+975</td><td>+1 400</td><td>+1 825</td><td class="pos"><b>+2 250</b></td><td>+600</td></tr>
        </tbody>
    </table>
    <div class="note">Группа обгоняет индивидуальный с 3 пришедших. Полная группа рентабельнее индивидуального в 3,75 раза. 58% групповых уроков прошли с ≤3 пришедшими.</div>
</div>

<h2><span class="n">3.</span>Ключевые находки</h2>

<div class="findings">
    <div class="finding opp">
        <b class="t">💰 Главный резерв — добивка групп: ≈ +95 000 ₽/мес</b>
        <div class="d">В существующих группах 28 свободных мест (средний размер 3,85 из 6). Каждое место = +425 ₽ маржи за урок × 8 уроков/мес. Потенциал больше всей текущей месячной маржи — без новых преподавателей и помещений.</div>
    </div>
    <div class="finding crit">
        <b class="t">⚠ Сезонная бомба: 41% учеников — выпускной 9 класс</b>
        <div class="d">15 из 37 активных учеников — 9 класс, уходят после ОГЭ. Воронка на замену слаба: 8 класс — 5 человек, 7 класс — 6. Без летнего набора осенью выручка падает почти вдвое.</div>
    </div>
    <div class="finding warn">
        <b class="t">📉 Отмены индивидуальных: 35% слотов</b>
        <div class="d">169 отмен на 317 проведённых за полгода (январь — 50%). Недополученная маржа ≈ 101 000 ₽. Группы при этом почти не отменяются.</div>
    </div>
    <div class="finding warn">
        <b class="t">👤 Руслан почти не загружен</b>
        <div class="d">Первые начисления — июнь 2026. Его колонка в расписании — готовый канал роста без увеличения часов владельца.</div>
    </div>
    <div class="finding warn">
        <b class="t">🧾 Гигиена данных</b>
        <div class="d">39 уроков мая без отметки посещаемости; приток новых учеников после ноября почти нулевой (3–1–0–7–0); фактические денежные поступления не сверяются с расчётной моделью (Gmail-парсинг мёртв с апреля).</div>
    </div>
</div>

<h2><span class="n">4.</span>Рекомендации (по убыванию эффекта)</h2>

<ol class="recs">
    <li><b>Кампания «добей группы до 5–6»</b><div class="why">Новых учеников сажать в существующие группы с 3–4 участниками, а не открывать новые. Потенциал ≈ +95k ₽/мес.</div></li>
    <li><b>Летний набор 7–9 классов (июнь–август)</b><div class="why">Закрыть дыру от выпуска 15 девятиклассников; нынешний 8 класс (5 чел.) — будущий 9-й, его надо удвоить-утроить.</div></li>
    <li><b>Политика отмен для индивидуальных</b><div class="why">Перенос минимум за 24 часа, иначе урок сгорает. Снижение отмен с 35% до 20% ≈ +7–8k ₽/мес.</div></li>
    <li><b>Группы из 1–2 человек — объединить или перевести на индив-прайс</b><div class="why">Урок с 1 пришедшим по групповой цене = −275 ₽; таких уроков было 15, с двумя — 37.</div></li>
    <li><b>Загрузить Руслана новыми группами</b><div class="why">Формулы те же — экономика идентична, рост без потолка часов владельца.</div></li>
    <li><b>Дисциплина отметок + решить судьбу учёта поступлений</b><div class="why">Отвечать боту в день урока; Gmail-парсинг чинить или удалить — полуданные хуже отсутствующих.</div></li>
</ol>

<div class="disclaimer">
    <b>Оговорка:</b> ~90% «выплат» периода — начисления владельцу (Станиславу), то есть внутренний доход, а не внешние издержки.
    Маржа в отчёте — операционная метрика сравнения типов уроков, а не чистая прибыль бизнеса. Внешняя зарплатная
    нагрузка появится по мере загрузки Руслана — тем важнее, что юнит-экономика групп (51%) уже сейчас лучше индивидуальных (43%).
    Аномалия: в декабре дополнительно к урочным начислениям были 2 ручные выплаты на 70 000 ₽ — в расчёт по формулам не входят.
</div>

<a class="backlink" href="/zarplata/">← Вернуться на дашборд</a>

</div>
</body>
</html>
