/**
 * JavaScript для отчётов и аналитики
 */

let dailyChart = null;
let teacherChart = null;

// При загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    loadReports();

    // Обработчик формы фильтров
    document.getElementById('filters-form').addEventListener('submit', (e) => {
        e.preventDefault();
        loadReports();
    });
});

// Загрузить все отчёты
async function loadReports() {
    const dateFrom = document.getElementById('date_from').value;
    const dateTo = document.getElementById('date_to').value;
    const teacherId = document.getElementById('teacher_id').value;

    // Загружаем сводку
    await loadSummary(dateFrom, dateTo);

    // Загружаем графики
    await loadDailyChart(dateFrom, dateTo);
    await loadTeacherChart(dateFrom, dateTo);

    // Если выбран конкретный преподаватель - показываем детали
    if (teacherId) {
        await loadTeacherDetails(teacherId, dateFrom, dateTo);
    } else {
        document.getElementById('teacher-details').style.display = 'none';
    }
}

// Загрузить сводную статистику
async function loadSummary(dateFrom, dateTo) {
    try {
        const response = await fetch(`/zarplata/api/reports.php?action=summary&date_from=${dateFrom}&date_to=${dateTo}`);
        const result = await response.json();

        if (result.success) {
            const data = result.data;
            renderSummary(data);
        } else {
            showNotification(result.error || 'Ошибка загрузки статистики', 'error');
        }
    } catch (error) {
        console.error('Error loading summary:', error);
        showNotification('Ошибка загрузки статистики', 'error');
    }
}

// Отрисовать сводную статистику
function renderSummary(data) {
    const summary = data.summary;
    const financial = data.financial;

    const html = `
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value">${summary.total_lessons || 0}</div>
                        <div class="stat-card-label">Всего уроков</div>
                    </div>
                    <div class="stat-card-icon primary">
                        <span class="material-icons">school</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value">${summary.completed_lessons || 0}</div>
                        <div class="stat-card-label">Завершено</div>
                    </div>
                    <div class="stat-card-icon success">
                        <span class="material-icons">check_circle</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value">${summary.total_students || 0}</div>
                        <div class="stat-card-label">Учеников обучено</div>
                    </div>
                    <div class="stat-card-icon info">
                        <span class="material-icons">groups</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value">${formatMoney(financial.total_amount || 0)}</div>
                        <div class="stat-card-label">Начислено</div>
                    </div>
                    <div class="stat-card-icon secondary">
                        <span class="material-icons">payments</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value">${formatMoney(financial.paid_total || 0)}</div>
                        <div class="stat-card-label">Выплачено</div>
                    </div>
                    <div class="stat-card-icon success">
                        <span class="material-icons">check_circle</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value">${formatMoney(financial.pending_total || 0)}</div>
                        <div class="stat-card-label">Ожидают</div>
                    </div>
                    <div class="stat-card-icon warning">
                        <span class="material-icons">pending</span>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.getElementById('summary-stats').innerHTML = html;
}

// Загрузить график по дням
async function loadDailyChart(dateFrom, dateTo) {
    try {
        const response = await fetch(`/zarplata/api/reports.php?action=daily_chart&date_from=${dateFrom}&date_to=${dateTo}`);
        const result = await response.json();

        if (result.success) {
            renderDailyChart(result.data);
        }
    } catch (error) {
        console.error('Error loading daily chart:', error);
    }
}

// Отрисовать график по дням
function renderDailyChart(data) {
    const ctx = document.getElementById('daily-chart');

    if (dailyChart) {
        dailyChart.destroy();
    }

    const labels = data.map(item => formatDate(item.date));
    const lessonsData = data.map(item => item.lessons_count);
    const studentsData = data.map(item => item.students_count);
    const revenueData = data.map(item => item.revenue);

    dailyChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Уроки',
                    data: lessonsData,
                    borderColor: 'rgb(187, 134, 252)',
                    backgroundColor: 'rgba(187, 134, 252, 0.1)',
                    yAxisID: 'y',
                    tension: 0.3
                },
                {
                    label: 'Ученики',
                    data: studentsData,
                    borderColor: 'rgb(33, 150, 243)',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    yAxisID: 'y',
                    tension: 0.3
                },
                {
                    label: 'Выручка (₽)',
                    data: revenueData,
                    borderColor: 'rgb(76, 175, 80)',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    yAxisID: 'y1',
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Количество'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)'
                    },
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.6)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Рубли'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.6)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)'
                    },
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.6)'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: 'rgba(255, 255, 255, 0.8)'
                    }
                }
            }
        }
    });
}

// Загрузить график по преподавателям
async function loadTeacherChart(dateFrom, dateTo) {
    try {
        const response = await fetch(`/zarplata/api/reports.php?action=teacher_chart&date_from=${dateFrom}&date_to=${dateTo}`);
        const result = await response.json();

        if (result.success) {
            renderTeacherChart(result.data);
        }
    } catch (error) {
        console.error('Error loading teacher chart:', error);
    }
}

// Отрисовать график по преподавателям
function renderTeacherChart(data) {
    const ctx = document.getElementById('teacher-chart');

    if (teacherChart) {
        teacherChart.destroy();
    }

    const labels = data.map(item => item.name);
    const earningsData = data.map(item => item.total_earned);

    teacherChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                label: 'Заработано',
                data: earningsData,
                backgroundColor: [
                    'rgba(187, 134, 252, 0.8)',
                    'rgba(3, 218, 198, 0.8)',
                    'rgba(76, 175, 80, 0.8)',
                    'rgba(255, 152, 0, 0.8)',
                    'rgba(33, 150, 243, 0.8)',
                    'rgba(244, 67, 54, 0.8)'
                ],
                borderColor: 'rgba(30, 30, 46, 1)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: 'rgba(255, 255, 255, 0.8)',
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = formatMoney(context.parsed);
                            return label + ': ' + value;
                        }
                    }
                }
            }
        }
    });
}

// Загрузить детали по преподавателю
async function loadTeacherDetails(teacherId, dateFrom, dateTo) {
    try {
        const response = await fetch(`/zarplata/api/reports.php?action=by_teacher&teacher_id=${teacherId}&date_from=${dateFrom}&date_to=${dateTo}`);
        const result = await response.json();

        if (result.success) {
            renderTeacherDetails(result.data);
        }
    } catch (error) {
        console.error('Error loading teacher details:', error);
    }
}

// Отрисовать детали по преподавателю
function renderTeacherDetails(data) {
    const teacher = data.teacher;
    const lessons = data.lessons;
    const payments = data.payments;

    let html = `
        <div class="card mb-4">
            <div class="card-header">
                <h3 style="margin: 0;">
                    <span class="material-icons" style="vertical-align: middle;">person</span>
                    Детали: ${escapeHtml(teacher.name)}
                </h3>
            </div>
            <div class="card-body">
                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-bottom: 24px;">
                    <div>
                        <div style="font-size: 1.25rem; font-weight: 500;">${lessons.total_lessons || 0}</div>
                        <div style="font-size: 0.875rem; color: var(--text-medium-emphasis);">Всего уроков</div>
                    </div>
                    <div>
                        <div style="font-size: 1.25rem; font-weight: 500;">${lessons.completed_lessons || 0}</div>
                        <div style="font-size: 0.875rem; color: var(--text-medium-emphasis);">Завершено</div>
                    </div>
                    <div>
                        <div style="font-size: 1.25rem; font-weight: 500;">${lessons.total_students || 0}</div>
                        <div style="font-size: 0.875rem; color: var(--text-medium-emphasis);">Обучено учеников</div>
                    </div>
                    <div>
                        <div style="font-size: 1.25rem; font-weight: 500;">${formatMoney(payments.total || 0)}</div>
                        <div style="font-size: 0.875rem; color: var(--text-medium-emphasis);">Всего начислено</div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.getElementById('teacher-details').innerHTML = html;
    document.getElementById('teacher-details').style.display = 'block';
}

// Экспорт в Excel
function exportToExcel() {
    const dateFrom = document.getElementById('date_from').value;
    const dateTo = document.getElementById('date_to').value;
    const teacherId = document.getElementById('teacher_id').value;

    let url = `/zarplata/api/reports.php?action=export_excel&date_from=${dateFrom}&date_to=${dateTo}`;
    if (teacherId) {
        url += `&teacher_id=${teacherId}`;
    }

    window.location.href = url;
}

// Утилиты
function formatMoney(amount) {
    if (!amount && amount !== 0) return '0 ₽';
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0
    }).format(amount);
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span class="material-icons">${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info'}</span>
        <span>${escapeHtml(message)}</span>
    `;

    document.body.appendChild(notification);

    setTimeout(() => notification.classList.add('show'), 10);

    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
