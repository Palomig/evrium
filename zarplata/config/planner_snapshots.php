<?php
/**
 * История версий расписания-планировщика (как в Google Таблицах).
 *
 * Версии создаются по внесённым изменениям, не по времени:
 * после каждого изменения через API вызывается plannerRecordVersion().
 * Если с последней правки прошло меньше PLANNER_VERSION_WINDOW_MIN минут,
 * текущая версия обновляется (правки одной сессии схлопываются в одну
 * версию); иначе создаётся новая. Каждая версия хранит полное состояние
 * сетки после изменения.
 */

const PLANNER_VERSION_WINDOW_MIN = 10;

function plannerNow() {
    $dt = new DateTime('now', new DateTimeZone('Europe/Moscow'));
    return $dt->format('Y-m-d H:i:s');
}

function ensurePlannerSnapshotsTable() {
    dbExecute("
        CREATE TABLE IF NOT EXISTS planner_snapshots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            data MEDIUMTEXT NOT NULL,
            KEY idx_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", []);
}

/**
 * Зафиксировать текущее состояние сетки как версию.
 * Вызывается после каждого изменения planner_notes.
 */
function plannerRecordVersion() {
    ensurePlannerSnapshotsTable();

    $notes = dbQuery("SELECT * FROM planner_notes ORDER BY day, time, room, kind, position, id", []);
    $data = json_encode($notes, JSON_UNESCAPED_UNICODE);
    $now = plannerNow();

    $last = dbQueryOne("SELECT id, updated_at FROM planner_snapshots ORDER BY updated_at DESC, id DESC LIMIT 1", []);

    if ($last) {
        $lastTs = strtotime($last['updated_at']);
        $nowTs = strtotime($now);
        if ($nowTs - $lastTs < PLANNER_VERSION_WINDOW_MIN * 60) {
            // Продолжение сессии правок — обновляем текущую версию
            dbExecute(
                "UPDATE planner_snapshots SET data = ?, updated_at = ? WHERE id = ?",
                [$data, $now, $last['id']]
            );
            return;
        }
    }

    dbExecute(
        "INSERT INTO planner_snapshots (created_at, updated_at, data) VALUES (?, ?, ?)",
        [$now, $now, $data]
    );
}
