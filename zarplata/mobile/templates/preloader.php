<?php
/**
 * Mobile Preloader «Рабочий день складывается»
 * Показывается один раз в день при первом открытии PWA.
 * Расписание, ученики и выплаты сходятся к знаку «Э», после чего открывается страница.
 * Демо-прототип: https://palomig.ru/preview/evrium-preloader/
 */
?>
    <div id="pl" class="pl" aria-live="polite" aria-label="Загрузка приложения">
        <div class="pl-brand"><span class="pl-brand-mark">Э</span><span>Эвриум</span></div>
        <div class="pl-orbit"></div>
        <div class="pl-wire pl-wire-top"></div><div class="pl-wire pl-wire-left"></div><div class="pl-wire pl-wire-right"></div>
        <i class="pl-pulse pl-pulse-top"></i><i class="pl-pulse pl-pulse-left"></i><i class="pl-pulse pl-pulse-right"></i>
        <div class="pl-hub">Э</div>
        <div class="pl-card pl-schedule">
            <div class="pl-card-head"><span class="pl-icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4m8-4v4M3 10h18"/></svg></span>Расписание</div>
            <div class="pl-days"><i></i><i></i><i></i><i></i><i></i><span class="pl-runner"></span></div>
            <small><span class="pl-process" data-label="Загружаем уроки">Загружаем уроки</span><span class="pl-dots" aria-hidden="true"></span></small>
        </div>
        <div class="pl-card pl-students">
            <div class="pl-card-head"><span class="pl-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3.5 19c.4-3.4 2.2-5 5.5-5s5.1 1.6 5.5 5M14 15c3.8-.8 6.1.6 6.5 4"/></svg></span>Ученики</div>
            <div class="pl-row"><span class="pl-process" data-label="Обновляем данные">Обновляем данные</span><span class="pl-dots" aria-hidden="true"></span></div>
        </div>
        <div class="pl-card pl-salary">
            <div class="pl-card-head"><span class="pl-icon"><svg viewBox="0 0 24 24"><ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg></span>Выплаты</div>
            <div class="pl-row"><span class="pl-process" data-label="Сверяем начисления">Сверяем начисления</span><span class="pl-dots" aria-hidden="true"></span></div>
        </div>
        <div class="pl-copy">
            <strong id="pl-status">Собираем рабочий день</strong>
            <span id="pl-substatus">Расписание · ученики · выплаты</span>
            <div class="pl-steps"><i></i><i class="active"></i><i></i></div>
        </div>
    </div>
    <style>
        .pl{position:fixed;inset:0;z-index:9999;overflow:hidden;background:#0b1017;color:#edf4f6;font-family:'Nunito',system-ui,sans-serif;isolation:isolate;transition:opacity .32s ease,transform .45s cubic-bezier(.2,.8,.2,1)}
        .pl:before{content:"";position:absolute;inset:-20%;background:radial-gradient(circle at 50% 47%,#0d494744 0 15%,transparent 40%);pointer-events:none}
        .pl.pl-hidden{display:none}
        .pl-brand{position:absolute;top:calc(24px + env(safe-area-inset-top,0px));left:24px;display:flex;align-items:center;gap:10px;font-weight:700;font-size:13px;color:#cdd7de}
        .pl-brand-mark{width:28px;height:28px;border-radius:9px;display:grid;place-items:center;background:#14b8a6;color:#fff;font-weight:800}
        .pl-orbit{position:absolute;width:250px;height:250px;left:50%;top:47%;transform:translate(-50%,-50%);border:1px dashed #41606c99;border-radius:50%;opacity:0;transition:opacity .35s ease}
        .pl-hub{position:absolute;z-index:4;left:50%;top:50%;width:92px;height:92px;transform:translate(-50%,-50%) scale(1.45);border-radius:50%;display:grid;place-items:center;background:#14b8a6;font-size:47px;font-weight:800;color:#fff;box-shadow:0 0 0 1px #58e0d100,0 16px 50px #0eb1a400;transition:top .6s cubic-bezier(.22,1,.36,1),border-radius .6s ease,background .6s ease,box-shadow .6s ease,transform .6s cubic-bezier(.22,1.15,.36,1)}
        .pl-card{position:absolute;z-index:3;width:154px;padding:13px;border:1px solid #2a3946;border-radius:15px;background:#151d27ee;box-shadow:0 14px 34px #0009;opacity:0;filter:blur(5px);transition:opacity .32s,filter .32s,transform .55s cubic-bezier(.22,1,.36,1)}
        .pl-schedule{left:50%;top:16%;transform:translate(-50%,-20px) scale(.92)}
        .pl-students{left:18px;top:65%;transform:translate(-18px,12px) scale(.92)}
        .pl-salary{right:18px;top:65%;transform:translate(18px,12px) scale(.92)}
        .pl-card-head{display:flex;align-items:center;gap:8px;font-size:11px;font-weight:700}
        .pl-icon{width:26px;height:26px;border:1px solid #159e96;border-radius:8px;display:grid;place-items:center;color:#28c9bb}
        .pl-icon svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
        .pl-card small{display:block;color:#778596;font-size:8px;margin-top:9px}
        .pl-days{position:relative;display:flex;gap:4px;margin-top:7px}
        .pl-days i{height:17px;flex:1;background:#202b37;border-radius:4px}
        .pl-runner{position:absolute;top:0;left:0;width:calc((100% - 16px)/5);height:17px;border-radius:4px;background:#19b7aa;box-shadow:0 0 12px #19b7aa55}
        .pl.enter .pl-runner{animation:plDayRun 1.6s linear infinite}
        .pl-row{margin-top:8px;padding:7px 8px;background:#1a2630;border-radius:7px;color:#b7c4cc;font-size:8px;display:flex;align-items:center}
        .pl-dots{display:inline-block;width:0;overflow:hidden;vertical-align:bottom;color:#19b7aa;animation:plDots 1.1s steps(4,end) infinite}
        .pl-dots:after{content:"..."}
        .pl.ready .pl-process{color:#68d9cf}
        .pl.ready .pl-dots{display:none}
        .pl-wire{position:absolute;z-index:1;height:1px;width:96px;top:47%;left:50%;transform-origin:left;background:repeating-linear-gradient(90deg,#50808b 0 3px,transparent 3px 8px);opacity:0}
        .pl-wire-top{transform:rotate(-90deg)}.pl-wire-left{transform:rotate(145deg)}.pl-wire-right{transform:rotate(35deg)}
        .pl-pulse{position:absolute;z-index:2;left:50%;top:47%;width:8px;height:8px;margin:-4px;border-radius:50%;background:#69f4e6;box-shadow:0 0 15px #2fe4d3;opacity:0}
        .pl-copy{position:absolute;left:20px;right:20px;bottom:calc(52px + env(safe-area-inset-bottom,0px));text-align:center}
        .pl-copy strong{display:block;font-size:15px}
        .pl-copy span{display:block;margin-top:7px;color:#778596;font-size:10px}
        .pl-steps{display:flex;justify-content:center;gap:7px;margin-top:14px}
        .pl-steps i{width:5px;height:5px;background:#41505e;border-radius:50%}
        .pl-steps i.active{background:#19b7aa;box-shadow:0 0 8px #18b7aa}
        .pl.enter .pl-hub{top:47%;transform:translate(-50%,-50%) scale(1);border-radius:27px;background:linear-gradient(145deg,#20c5b8,#0d8f87);box-shadow:0 0 0 1px #58e0d166,0 16px 50px #0eb1a438}
        .pl-brand,.pl-copy{opacity:0;transition:opacity .5s ease .15s}
        .pl.enter .pl-brand,.pl.enter .pl-copy,.pl.enter .pl-orbit,.pl.enter .pl-wire{opacity:1}
        .pl.enter .pl-card{opacity:1;filter:none;transform:translate(0) scale(1)}
        .pl.enter .pl-schedule{transform:translateX(-50%) scale(1)}
        .pl.transfer .pl-pulse{opacity:1}
        .pl.transfer .pl-pulse-top{animation:plPulseTop 1.4s ease-in-out infinite}
        .pl.transfer .pl-pulse-left{animation:plPulseLeft 1.4s -.22s ease-in-out infinite}
        .pl.transfer .pl-pulse-right{animation:plPulseRight 1.4s -.44s ease-in-out infinite}
        .pl.collapse .pl-card{opacity:0;filter:blur(4px)}
        .pl.collapse .pl-schedule{transform:translate(-50%,150px) scale(.35)}
        .pl.collapse .pl-students{transform:translate(92px,-105px) scale(.35)}
        .pl.collapse .pl-salary{transform:translate(-92px,-105px) scale(.35)}
        .pl.collapse .pl-orbit,.pl.collapse .pl-wire,.pl.collapse .pl-pulse{opacity:0}
        .pl.collapse .pl-hub{animation:plHubDone .42s .18s ease-in-out}
        .pl.done{opacity:0;transform:scale(1.04);pointer-events:none}
        @keyframes plDots{to{width:1.5em}}
        @keyframes plDayRun{0%,19.99%{transform:translateX(0)}20%,39.99%{transform:translateX(calc(100% + 4px))}40%,59.99%{transform:translateX(calc(200% + 8px))}60%,79.99%{transform:translateX(calc(300% + 12px))}80%,100%{transform:translateX(calc(400% + 16px))}}
        @keyframes plPulseTop{0%,100%{transform:translate(0,0)}50%{transform:translate(0,-92px)}}
        @keyframes plPulseLeft{0%,100%{transform:translate(0,0)}50%{transform:translate(-80px,55px)}}
        @keyframes plPulseRight{0%,100%{transform:translate(0,0)}50%{transform:translate(80px,55px)}}
        @keyframes plHubDone{50%{transform:translate(-50%,-50%) scale(1.12);box-shadow:0 0 0 10px #16b8aa18,0 16px 50px #0eb1a45c}}
        @media (prefers-reduced-motion:reduce){.pl,.pl *{animation-duration:.01ms!important;animation-delay:0ms!important;transition-duration:.01ms!important}.pl-pulse{display:none}}
    </style>
    <script>
    (function () {
        var pl = document.getElementById('pl');
        if (!pl) return;

        var KEY = 'zarplata_mobile_preloader_date';
        var today = new Date().toDateString();
        var lastShown = null;
        try { lastShown = localStorage.getItem(KEY); } catch (e) {}

        // Уже показывали сегодня — сразу убираем, страница отрисуется без задержки
        if (lastShown === today) {
            pl.classList.add('pl-hidden');
            return;
        }
        try { localStorage.setItem(KEY, today); } catch (e) {}

        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var MIN_LOAD_MS = reduced ? 1200 : 5200;   // полный цикл анимации
        var MAX_WAIT_MS = 9000;                    // страховка: дольше не держим
        var startedAt = Date.now();
        var finished = false;
        var status = document.getElementById('pl-status');
        var sub = document.getElementById('pl-substatus');

        function add(cls) { pl.classList.add(cls); }
        function ready() {
            add('ready');
            var nodes = pl.querySelectorAll('.pl-process');
            for (var i = 0; i < nodes.length; i++) nodes[i].textContent = 'Готово';
        }
        function finish() {
            if (finished) return;
            finished = true;
            ready();
            setTimeout(function () { add('collapse'); }, 450);
            setTimeout(function () { add('done'); }, 900);
            setTimeout(function () { add('pl-hidden'); }, 1400);
        }
        // Завершаем, когда прошёл минимальный цикл И страница догрузилась
        function tryFinish() {
            var elapsed = Date.now() - startedAt;
            var remaining = MIN_LOAD_MS - 900 - elapsed;
            if (remaining > 0) { setTimeout(tryFinish, remaining); return; }
            if (document.readyState === 'complete' || elapsed >= MAX_WAIT_MS) { finish(); return; }
            status.textContent = 'Почти готово';
            sub.textContent = 'Синхронизируем последние изменения';
            window.addEventListener('load', finish, { once: true });
            setTimeout(finish, MAX_WAIT_MS - elapsed);
        }

        setTimeout(function () { add('enter'); }, 380);   // первый кадр = «Э» со сплэша, затем знак садится в хаб
        setTimeout(function () { add('transfer'); }, 1100);
        tryFinish();
    })();
    </script>
