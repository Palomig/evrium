/**
 * app.js
 * Главный модуль приложения
 *
 * Инициализация и роутинг
 */

console.log('Интерактивная геометрия загружена');

// Проверяем поддержку ES6 модулей
if ('noModule' in HTMLScriptElement.prototype) {
    console.log('ES6 модули поддерживаются');
} else {
    console.warn('ES6 модули не поддерживаются этим браузером');
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM загружен');

    // Добавляем обработчики для навигации
    initNavigation();

    // Проверяем поддержку SVG
    if (!document.implementation.hasFeature("http://www.w3.org/TR/SVG11/feature#BasicStructure", "1.1")) {
        console.error('SVG не поддерживается этим браузером');
        showError('Ваш браузер не поддерживает SVG. Пожалуйста, используйте современный браузер.');
    }
});

/**
 * Инициализирует навигацию
 */
function initNavigation() {
    // Подсветка активного пункта меню
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');

    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (currentPath.includes(href)) {
            link.classList.add('active');
        }
    });
}

/**
 * Показывает ошибку пользователю
 */
function showError(message) {
    const body = document.body;
    const errorDiv = document.createElement('div');
    errorDiv.className = 'alert alert-danger m-3';
    errorDiv.innerHTML = `
        <h4>Ошибка</h4>
        <p>${message}</p>
    `;
    body.insertBefore(errorDiv, body.firstChild);
}

// Экспортируем утилиты
export { showError };
