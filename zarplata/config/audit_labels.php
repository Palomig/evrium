<?php
/**
 * Человеческие подписи для журнала аудита: действие → фраза, объект → имя.
 * Используется мобильной и десктопной страницами «Аудит».
 */

require_once __DIR__ . '/db.php';

/**
 * Подпись действия
 * @param string $action action_type
 * @return string
 */
function auditActionLabel($action) {
    static $labels = [
        // вход и пользователи
        'user_login'              => 'Вход в систему',
        'user_logout'             => 'Выход из системы',
        'user_created'            => 'Добавлен пользователь',
        'user_updated'            => 'Изменены права пользователя',
        'password_changed'        => 'Сменён пароль',
        'telegram_linked'         => 'Привязан Telegram',
        'telegram_login_rejected' => 'Попытка входа с чужого Telegram',
        'device_revoked'          => 'Отвязано устройство',
        // преподаватели
        'teacher_created'         => 'Добавлен преподаватель',
        'teacher_updated'         => 'Изменён преподаватель',
        'teacher_deactivated'     => 'Преподаватель отключён',
        'teacher_deleted'         => 'Преподаватель удалён',
        'clear_teachers'          => 'Очищен список преподавателей',
        // ученики
        'student_created'         => 'Добавлен ученик',
        'student_updated'         => 'Изменён ученик',
        'student_activated'       => 'Ученик снова активен',
        'student_deactivated'     => 'Ученик отключён',
        'student_deleted'         => 'Ученик удалён',
        'student_schedule_added'  => 'Ученик добавлен в расписание',
        'student_schedule_moved'  => 'Ученик перенесён в расписании',
        'student_slot_removed'    => 'Ученик убран из урока',
        'students_migrated'       => 'Ученики перенесены в расписание',
        'students_sync'           => 'Пересчитано число учеников в группах',
        'clear_students'          => 'Очищен список учеников',
        // расписание и уроки
        'template_created'        => 'Добавлен урок в расписание',
        'template_updated'        => 'Изменён урок в расписании',
        'template_moved'          => 'Урок перенесён в расписании',
        'template_deleted'        => 'Урок убран из расписания',
        'template_auto_created'   => 'Урок добавлен в расписание автоматически',
        'template_auto_deactivated' => 'Урок снят с расписания автоматически',
        'week_generated'          => 'Сформирована неделя уроков',
        'lesson_created'          => 'Создан урок',
        'lesson_updated'          => 'Изменён урок',
        'lesson_completed'        => 'Урок проведён',
        'lesson_cancelled'        => 'Урок отменён',
        'lesson_deleted'          => 'Урок удалён',
        'attendance_marked'       => 'Отмечена посещаемость',
        'attendance_submitted'    => 'Отправлена посещаемость',
        // деньги
        'payment_created'         => 'Начислена выплата',
        'payment_updated'         => 'Изменена выплата',
        'payment_approved'        => 'Выплата подтверждена',
        'payment_paid'            => 'Выплата выдана',
        'payment_cancelled'       => 'Выплата отменена',
        'payment_deleted'         => 'Выплата удалена',
        'payments_cleared_all'    => 'Удалены все выплаты',
        'payout_created'          => 'Создан расчёт к выплате',
        'student_payment_added'   => 'Внесена оплата ученика',
        'student_payment_deleted' => 'Удалена оплата ученика',
        'incoming_payment_received'  => 'Поступила оплата',
        'incoming_payment_matched'   => 'Оплата сопоставлена с учеником',
        'incoming_payment_confirmed' => 'Оплата подтверждена',
        'cash_payment_added'      => 'Внесена оплата наличными',
        'cash_payment_deleted'    => 'Удалена оплата наличными',
        'payer_created'           => 'Добавлен плательщик',
        // формулы и настройки
        'formula_created'         => 'Создана формула оплаты',
        'formula_updated'         => 'Изменена формула оплаты',
        'formula_activated'       => 'Формула включена',
        'formula_deactivated'     => 'Формула выключена',
        'formula_deleted'         => 'Формула удалена',
        'settings_updated'        => 'Изменены настройки',
        'telegram_webhook_setup'  => 'Настроена связь с Telegram-ботом',
        'api_token_regenerated'   => 'Обновлён ключ API',
        'migration_applied'       => 'Обновлена структура базы',
        'bot_registration_attempt'=> 'Незнакомый человек написал боту',
    ];
    if (isset($labels[$action])) {
        return $labels[$action];
    }
    // Неизвестное действие — хотя бы по-русски без подчёркиваний
    return mb_convert_case(str_replace('_', ' ', (string)$action), MB_CASE_TITLE, 'UTF-8');
}

/**
 * Имя объекта, над которым совершено действие («Настя», «Математика, вт 18:00, Руслан»)
 * @param string $type entity_type
 * @param int|null $id entity_id
 * @return string пусто, если объект не найден
 */
function auditEntityName($type, $id) {
    static $cache = [];
    $id = (int)$id;
    if (!$id) {
        return '';
    }
    $key = "$type:$id";
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    static $days = [1 => 'пн', 2 => 'вт', 3 => 'ср', 4 => 'чт', 5 => 'пт', 6 => 'сб', 7 => 'вс'];
    $name = '';
    switch ($type) {
        case 'student':
            $r = dbQueryOne("SELECT name FROM students WHERE id = ?", [$id]);
            $name = $r['name'] ?? '';
            break;
        case 'teacher':
            $r = dbQueryOne("SELECT COALESCE(display_name, name) AS n FROM teachers WHERE id = ?", [$id]);
            $name = $r['n'] ?? '';
            break;
        case 'user':
            $r = dbQueryOne("SELECT name FROM users WHERE id = ?", [$id]);
            $name = $r['name'] ?? '';
            break;
        case 'formula':
            $r = dbQueryOne("SELECT name FROM payment_formulas WHERE id = ?", [$id]);
            $name = $r['name'] ?? '';
            break;
        case 'template':
            $r = dbQueryOne(
                "SELECT lt.subject, lt.day_of_week, lt.time_start, COALESCE(t.display_name, t.name) AS teacher
                 FROM lessons_template lt LEFT JOIN teachers t ON t.id = lt.teacher_id WHERE lt.id = ?",
                [$id]
            );
            if ($r) {
                $name = trim(($r['subject'] ?: 'Урок') . ', ' . ($days[(int)$r['day_of_week']] ?? '') . ' ' . substr($r['time_start'], 0, 5) . ($r['teacher'] ? ', ' . $r['teacher'] : ''));
            }
            break;
        case 'lesson':
        case 'lesson_schedule':
            $r = dbQueryOne(
                "SELECT li.subject, li.lesson_date, li.time_start, COALESCE(t.display_name, t.name) AS teacher
                 FROM lessons_instance li LEFT JOIN teachers t ON t.id = li.teacher_id WHERE li.id = ?",
                [$id]
            );
            if ($r) {
                $name = trim(($r['subject'] ?: 'Урок') . ', ' . date('d.m', strtotime($r['lesson_date'])) . ' ' . substr($r['time_start'], 0, 5) . ($r['teacher'] ? ', ' . $r['teacher'] : ''));
            }
            break;
        case 'payment':
            $r = dbQueryOne(
                "SELECT p.amount, COALESCE(t.display_name, t.name) AS teacher
                 FROM payments p LEFT JOIN teachers t ON t.id = p.teacher_id WHERE p.id = ?",
                [$id]
            );
            if ($r) {
                $name = number_format((float)$r['amount'], 0, ',', ' ') . ' ₽' . ($r['teacher'] ? ' — ' . $r['teacher'] : '');
            }
            break;
    }
    return $cache[$key] = (string)$name;
}

/**
 * Собрать читаемое описание записи аудита
 * @param array $log строка audit_log (+ user_name из JOIN, если есть)
 * @return array ['title' => ..., 'subject' => ..., 'details' => ..., 'who' => ...]
 */
function auditDescribe(array $log) {
    $title = auditActionLabel($log['action_type'] ?? '');
    $subject = auditEntityName($log['entity_type'] ?? '', $log['entity_id'] ?? null);

    $details = trim((string)($log['notes'] ?? ''));
    // Короткие служебные заметки без конкретики («Создан новый преподаватель»,
    // «Обновление пользователя») лишь повторяют подпись действия — прячем
    if ($details !== '' && mb_strlen($details) <= 40 && !preg_match('/[0-9:@«"]/u', $details)
        && preg_match('/^(создан|добавлен|обновл|измен|удал|вход|выход|пароль|отмен)/iu', $details)) {
        $details = '';
    }
    // Подробности из new_value для типовых действий без заметки
    if ($details === '' && !empty($log['new_value'])) {
        $data = json_decode($log['new_value'], true);
        if (is_array($data)) {
            if (($log['action_type'] ?? '') === 'user_login' && !empty($data['via'])) {
                $details = $data['via'] === 'device' ? 'С привязанного устройства' . (!empty($data['device']) ? ' · ' . $data['device'] : '') : ($data['via'] === 'telegram' ? 'Через Telegram' : '');
            } elseif (($log['action_type'] ?? '') === 'device_revoked' && !empty($data['label'])) {
                $details = $data['label'];
            } elseif (($log['action_type'] ?? '') === 'telegram_login_rejected') {
                $details = trim('Telegram ' . (!empty($data['username']) ? '@' . $data['username'] : '') . ' (ID ' . ($data['telegram_id'] ?? '?') . ')');
            } elseif (isset($data['name']) && is_string($data['name']) && $subject === '') {
                $subject = $data['name'];
            }
        }
    }

    $who = trim((string)($log['user_name'] ?? ''));
    if ($subject !== '' && $subject === $who) {
        $subject = ''; // «Вход в систему — Руслан — Руслан»
    }
    return ['title' => $title, 'subject' => $subject, 'details' => $details, 'who' => $who];
}
