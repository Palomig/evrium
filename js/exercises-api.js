/**
 * exercises-api.js
 * API для загрузки и сохранения упражнений
 *
 * Этот модуль предоставляет функции для работы с JSON-файлами упражнений
 */

// Список доступных упражнений
const AVAILABLE_EXERCISES = [
    '001-parallel-transversal',
    '002-triangle-area'
];

/**
 * Загружает упражнение по ID
 * @param {string} id - ID упражнения (например, '001-parallel-transversal')
 * @returns {Promise<Object>} - Объект упражнения
 */
export async function loadExercise(id) {
    try {
        const response = await fetch(`exercises/${id}.json`);

        if (!response.ok) {
            throw new Error(`Упражнение ${id} не найдено`);
        }

        const exercise = await response.json();
        return exercise;
    } catch (error) {
        console.error('Ошибка загрузки упражнения:', error);
        throw error;
    }
}

/**
 * Загружает все доступные упражнения
 * @returns {Promise<Array>} - Массив объектов упражнений
 */
export async function loadAllExercises() {
    const exercises = [];

    for (const id of AVAILABLE_EXERCISES) {
        try {
            const exercise = await loadExercise(id);
            exercises.push(exercise);
        } catch (error) {
            console.warn(`Не удалось загрузить упражнение ${id}:`, error);
        }
    }

    return exercises;
}

/**
 * Валидирует JSON упражнения
 * @param {Object} exercise - Объект упражнения для валидации
 * @returns {Object} - {valid: boolean, errors: Array<string>}
 */
export function validateExercise(exercise) {
    const errors = [];

    // Проверяем обязательные поля
    if (!exercise.id) {
        errors.push('Отсутствует поле "id"');
    }

    if (!exercise.title) {
        errors.push('Отсутствует поле "title"');
    }

    if (!exercise.class) {
        errors.push('Отсутствует поле "class"');
    }

    if (!exercise.description) {
        errors.push('Отсутствует поле "description"');
    }

    if (!exercise.svg) {
        errors.push('Отсутствует поле "svg"');
    } else {
        if (!exercise.svg.width || !exercise.svg.height) {
            errors.push('В поле "svg" отсутствуют width или height');
        }

        if (!exercise.svg.elements || !Array.isArray(exercise.svg.elements)) {
            errors.push('В поле "svg" отсутствует массив "elements"');
        }
    }

    if (!exercise.hints || !Array.isArray(exercise.hints)) {
        errors.push('Отсутствует массив "hints"');
    }

    return {
        valid: errors.length === 0,
        errors: errors
    };
}

/**
 * Сохраняет упражнение в локальное хранилище
 * @param {Object} exercise - Объект упражнения
 */
export function saveExerciseToLocalStorage(exercise) {
    try {
        const key = `exercise_${exercise.id}`;
        localStorage.setItem(key, JSON.stringify(exercise));
        console.log(`Упражнение ${exercise.id} сохранено в локальное хранилище`);
    } catch (error) {
        console.error('Ошибка сохранения в локальное хранилище:', error);
    }
}

/**
 * Загружает упражнение из локального хранилища
 * @param {string} id - ID упражнения
 * @returns {Object|null} - Объект упражнения или null
 */
export function loadExerciseFromLocalStorage(id) {
    try {
        const key = `exercise_${id}`;
        const data = localStorage.getItem(key);

        if (data) {
            return JSON.parse(data);
        }

        return null;
    } catch (error) {
        console.error('Ошибка загрузки из локального хранилища:', error);
        return null;
    }
}

/**
 * Скачивает упражнение как JSON файл
 * @param {Object} exercise - Объект упражнения
 */
export function downloadExercise(exercise) {
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(exercise, null, 2));
    const downloadAnchor = document.createElement('a');
    downloadAnchor.setAttribute("href", dataStr);
    downloadAnchor.setAttribute("download", `${exercise.id}.json`);
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    document.body.removeChild(downloadAnchor);
}

/**
 * Загружает упражнение из файла
 * @param {File} file - Файл для загрузки
 * @returns {Promise<Object>} - Объект упражнения
 */
export async function loadExerciseFromFile(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = (e) => {
            try {
                const exercise = JSON.parse(e.target.result);
                resolve(exercise);
            } catch (error) {
                reject(new Error('Некорректный JSON файл'));
            }
        };

        reader.onerror = () => {
            reject(new Error('Ошибка чтения файла'));
        };

        reader.readAsText(file);
    });
}
