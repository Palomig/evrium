<?php
/**
 * Страница исправления проблемных шаблонов после миграции
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requireAuth();
$user = getCurrentUser();

// ID проблемных шаблонов из миграции
$problematicTemplateIds = [47, 49, 50, 51, 52, 53, 56, 57, 62, 63, 68, 69, 72, 73];

// Получить проблемные шаблоны
$placeholders = str_repeat('?,', count($problematicTemplateIds) - 1) . '?';
$templates = dbQuery(
    "SELECT lt.*, t.name as teacher_name
     FROM lessons_template lt
     LEFT JOIN teachers t ON lt.teacher_id = t.id
     WHERE lt.id IN ($placeholders)
     ORDER BY lt.id",
    $problematicTemplateIds
);

// Получить всех учеников для справки
$students = dbQuery(
    "SELECT id, name, class FROM students WHERE active = 1 ORDER BY name, class",
    []
);

// Группировка учеников по именам
$studentsByName = [];
foreach ($students as $student) {
    $name = $student['name'];
    if (!isset($studentsByName[$name])) {
        $studentsByName[$name] = [];
    }
    $studentsByName[$name][] = $student;
}

define('PAGE_TITLE', 'Исправление шаблонов');
define('PAGE_SUBTITLE', 'Ручное исправление неоднозначных учеников после миграции');
define('ACTIVE_PAGE', 'tests');

require_once __DIR__ . '/templates/header.php';
?>

<style>
    .template-card {
        background: var(--bg-elevated);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .template-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border);
    }
    .template-id {
        font-size: 18px;
        font-weight: 700;
        color: var(--accent);
    }
    .student-list {
        margin: 16px 0;
    }
    .student-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: var(--bg-hover);
        border-radius: 8px;
        margin-bottom: 8px;
    }
    .student-name {
        flex: 1;
        font-weight: 500;
    }
    .student-options {
        display: flex;
        gap: 8px;
    }
    .class-btn {
        padding: 6px 12px;
        border-radius: 6px;
        border: 2px solid var(--border);
        background: var(--bg-elevated);
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.2s;
    }
    .class-btn:hover {
        border-color: var(--accent);
        background: var(--accent-dim);
        color: var(--accent);
    }
    .class-btn.selected {
        border-color: var(--accent);
        background: var(--accent);
        color: white;
    }
</style>

<div class="page-header">
    <h1 class="page-title"><?= PAGE_TITLE ?></h1>
    <p class="page-subtitle"><?= PAGE_SUBTITLE ?></p>
</div>

<div style="margin-bottom: 20px; padding: 16px; background: rgba(251, 191, 36, 0.1); border-radius: 8px; color: #fbbf24;">
    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
        <span class="material-icons">info</span>
        <strong>Инструкция</strong>
    </div>
    <div style="font-size: 0.875rem; line-height: 1.5;">
        Ниже показаны шаблоны с неоднозначными учениками (одинаковые имена в разных классах).
        <br>Для каждого ученика выберите правильный класс и нажмите "Сохранить шаблон".
    </div>
</div>

<?php foreach ($templates as $template): ?>
    <?php
    $students_json = $template['students'];
    $students_array = json_decode($students_json, true) ?: [];

    $daysMap = ['', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];
    $dayName = $daysMap[$template['day_of_week']] ?? '';
    ?>

    <div class="template-card">
        <div class="template-header">
            <div>
                <span class="template-id">Шаблон #<?= $template['id'] ?></span>
                <div style="font-size: 14px; color: var(--text-medium-emphasis); margin-top: 4px;">
                    <?= e($template['teacher_name']) ?> • <?= $dayName ?> • <?= substr($template['time_start'], 0, 5) ?> • <?= e($template['subject']) ?>
                </div>
            </div>
        </div>

        <div class="student-list">
            <?php foreach ($students_array as $index => $studentName): ?>
                <?php
                // Проверяем, есть ли уже класс в скобках
                $hasClass = preg_match('/\((\d+)\s*кл\.\)/', $studentName);

                // Извлекаем чистое имя
                $cleanName = preg_replace('/\s*\(\d+\s*кл\.\)\s*/', '', $studentName);

                // Находим возможные варианты этого ученика
                $possibleStudents = $studentsByName[$cleanName] ?? [];
                ?>

                <div class="student-item">
                    <div class="student-name">
                        <?= e($cleanName) ?>
                        <?php if ($hasClass): ?>
                            <span style="color: var(--accent); margin-left: 8px;">✓ уже исправлен</span>
                        <?php else: ?>
                            <span style="color: var(--md-warning); margin-left: 8px;">⚠ требует исправления</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!$hasClass && count($possibleStudents) > 1): ?>
                        <div class="student-options">
                            <span style="font-size: 13px; color: var(--text-disabled); margin-right: 8px;">Выберите класс:</span>
                            <?php foreach ($possibleStudents as $s): ?>
                                <button class="class-btn"
                                        onclick="selectClass(<?= $template['id'] ?>, <?= $index ?>, '<?= e($s['name']) ?>', <?= $s['class'] ?>)">
                                    <?= $s['class'] ?> кл.
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (!$hasClass): ?>
                        <span style="color: var(--text-disabled); font-size: 13px;">
                            Только 1 вариант в БД
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="display: flex; gap: 12px; margin-top: 16px;">
            <button class="btn btn-primary" onclick="openEditModal(<?= $template['id'] ?>)">
                <span class="material-icons" style="font-size: 18px;">edit</span>
                Редактировать в расписании
            </button>
            <button class="btn btn-secondary" onclick="viewTemplateData(<?= $template['id'] ?>)">
                <span class="material-icons" style="font-size: 18px;">code</span>
                Показать JSON
            </button>
        </div>
    </div>
<?php endforeach; ?>

<script>
    async function selectClass(templateId, studentIndex, studentName, studentClass) {
        const button = event.target;
        const originalText = button.textContent;

        // Подтверждение
        if (!confirm(`Установить для ученика "${studentName}" класс ${studentClass}?`)) {
            return;
        }

        // Блокируем кнопку
        button.disabled = true;
        button.textContent = '...';

        try {
            const response = await fetch('/zarplata/api/update_student_in_template.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    template_id: templateId,
                    student_index: studentIndex,
                    student_name: studentName,
                    student_class: studentClass
                })
            });

            const result = await response.json();

            if (result.success) {
                // Успешно обновлено
                button.classList.add('selected');
                button.textContent = '✓ ' + studentClass + ' кл.';

                // Показываем уведомление
                showNotification('Ученик успешно обновлён!', 'success');

                // Обновляем статус ученика в интерфейсе
                const studentItem = button.closest('.student-item');
                const statusSpan = studentItem.querySelector('.student-name span');
                if (statusSpan) {
                    statusSpan.textContent = '✓ исправлен';
                    statusSpan.style.color = 'var(--accent)';
                }

                // Убираем другие кнопки выбора класса
                const studentOptions = button.closest('.student-options');
                if (studentOptions) {
                    const otherButtons = studentOptions.querySelectorAll('.class-btn:not(.selected)');
                    otherButtons.forEach(btn => btn.remove());
                }
            } else {
                throw new Error(result.error || 'Ошибка обновления');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Ошибка: ' + error.message, 'error');
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    function openEditModal(templateId) {
        // Перенаправляем на страницу расписания с открытием модалки редактирования
        window.location.href = `/zarplata/schedule.php#edit-${templateId}`;
    }

    function viewTemplateData(templateId) {
        alert('Функция просмотра JSON в разработке');
    }

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 16px 24px;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 10000;
            font-size: 14px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        `;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
</script>

<style>
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
</style>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
