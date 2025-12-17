<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Геометрические прототипы ОГЭ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 30px;
            color: #e4e4e4;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #fff;
            font-weight: 300;
            font-size: 2.2rem;
        }

        .controls {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .btn-success:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(17, 153, 142, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
        }

        .btn-danger:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(235, 51, 73, 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .btn-info:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(79, 172, 254, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .btn-warning:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(240, 147, 251, 0.4);
        }

        .table-wrapper {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 20px;
            overflow-x: auto;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            vertical-align: top;
        }

        th {
            background: rgba(102, 126, 234, 0.3);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
            color: #a8b2d1;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        th:first-child {
            border-radius: 8px 0 0 0;
        }

        th:last-child {
            border-radius: 0 8px 0 0;
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        td[contenteditable="true"] {
            cursor: text;
            transition: all 0.3s ease;
            border-radius: 4px;
            min-width: 80px;
            line-height: 1.5;
        }

        td[contenteditable="true"]:hover {
            background: rgba(102, 126, 234, 0.2);
        }

        td[contenteditable="true"]:focus {
            outline: none;
            background: rgba(102, 126, 234, 0.3);
            box-shadow: inset 0 0 0 2px #667eea;
        }

        td.modified {
            background: rgba(17, 153, 142, 0.2) !important;
        }

        .col-num {
            width: 120px;
            white-space: nowrap;
        }

        .col-type {
            width: 250px;
        }

        .col-method {
            min-width: 400px;
        }

        .image-cell {
            width: 160px;
            text-align: center;
        }

        .image-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .image-preview {
            width: 140px;
            height: 100px;
            border-radius: 8px;
            object-fit: cover;
            display: none;
            border: 2px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
            cursor: pointer;
        }

        .image-preview:hover {
            transform: scale(1.1);
            z-index: 100;
            position: relative;
        }

        .image-preview.visible {
            display: block;
        }

        .image-upload-btn {
            padding: 8px 14px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px dashed rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            color: #a8b2d1;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.3s ease;
        }

        .image-upload-btn:hover {
            background: rgba(102, 126, 234, 0.3);
            border-color: #667eea;
        }

        .image-input {
            display: none;
        }

        .delete-btn {
            background: rgba(235, 51, 73, 0.2);
            border: none;
            color: #f45c43;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .delete-btn:hover {
            background: rgba(235, 51, 73, 0.4);
        }

        .remove-image-btn {
            background: rgba(235, 51, 73, 0.2);
            border: none;
            color: #f45c43;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 10px;
            display: none;
        }

        .remove-image-btn.visible {
            display: inline-block;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .notification.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .notification.error {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }

        .actions-cell {
            width: 70px;
            text-align: center;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal.show {
            display: flex;
        }

        .modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 12px;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            cursor: pointer;
        }

        .stats {
            margin-top: 20px;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            display: flex;
            gap: 30px;
            font-size: 14px;
            color: #a8b2d1;
            flex-wrap: wrap;
        }

        .stats span {
            color: #667eea;
            font-weight: 600;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #a8b2d1;
        }

        .loading::after {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #667eea;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
            margin-left: 10px;
            vertical-align: middle;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Adaptive */
        @media (max-width: 1200px) {
            body {
                padding: 15px;
            }

            th, td {
                padding: 10px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Геометрические прототипы ОГЭ</h1>

        <div class="controls">
            <button class="btn btn-primary" onclick="addRow()" id="btnAdd">
                + Добавить строку
            </button>
            <button class="btn btn-success" onclick="saveTable()" id="btnSave">
                Сохранить
            </button>
            <button class="btn btn-info" onclick="loadTable()" id="btnLoad">
                Обновить
            </button>
            <button class="btn btn-warning" onclick="resetToDefault()" id="btnReset">
                Сбросить к исходному
            </button>
            <button class="btn btn-danger" onclick="clearTable()" id="btnClear">
                Очистить всё
            </button>
            <button class="btn btn-info" onclick="exportJSON()" id="btnExport">
                Экспорт JSON
            </button>
        </div>

        <div class="table-wrapper">
            <table id="editableTable">
                <thead>
                    <tr>
                        <th class="col-num">№ прототипов</th>
                        <th class="col-type">Геометрический тип задачи</th>
                        <th class="col-method">Ключевой принцип и метод решения</th>
                        <th>Изображение</th>
                        <th class="actions-cell">Действия</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="5" class="loading">Загрузка данных</td></tr>
                </tbody>
            </table>
        </div>

        <div class="stats">
            <div>Всего прототипов: <span id="rowCount">0</span></div>
            <div>С изображениями: <span id="imageCount">0</span></div>
            <div>Источник: <span id="dataSource">SQLite</span></div>
        </div>
    </div>

    <div class="notification" id="notification"></div>

    <div class="modal" id="imageModal" onclick="closeModal()">
        <span class="modal-close">&times;</span>
        <img id="modalImage" src="" alt="Увеличенное изображение">
    </div>

    <script>
        // API base URL
        const API_URL = 'api/prototypes.php';

        // Current data from DB
        let prototypes = [];
        let modifiedRows = new Set();

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadTable();
        });

        /**
         * API Helper
         */
        async function apiCall(action, data = null) {
            const url = `${API_URL}?action=${action}`;
            const options = {
                method: data ? 'POST' : 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            };

            if (data) {
                options.body = JSON.stringify(data);
            }

            const response = await fetch(url, options);
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Unknown error');
            }

            return result.data;
        }

        /**
         * Load data from database
         */
        async function loadTable() {
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '<tr><td colspan="5" class="loading">Загрузка данных</td></tr>';

            try {
                prototypes = await apiCall('list');
                renderTable();
                modifiedRows.clear();
                showNotification('Данные загружены из базы', 'info');
            } catch (error) {
                console.error('Load error:', error);
                tbody.innerHTML = '<tr><td colspan="5" style="color: #f45c43;">Ошибка загрузки: ' + error.message + '</td></tr>';
                showNotification('Ошибка загрузки: ' + error.message, 'error');
            }
        }

        /**
         * Render table from prototypes array
         */
        function renderTable() {
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '';

            prototypes.forEach((rowData, index) => {
                const row = createRowElement(rowData, index);
                tbody.appendChild(row);
            });

            updateStats();
        }

        /**
         * Create table row element
         */
        function createRowElement(rowData, index) {
            const row = document.createElement('tr');
            row.dataset.id = rowData.id || 'new_' + index;
            row.dataset.index = index;

            row.innerHTML = `
                <td class="col-num" contenteditable="true">${escapeHtml(rowData.num || '')}</td>
                <td class="col-type" contenteditable="true">${escapeHtml(rowData.type || '')}</td>
                <td class="col-method" contenteditable="true">${escapeHtml(rowData.method || '')}</td>
                <td class="image-cell">
                    <div class="image-container">
                        <img class="image-preview ${rowData.image ? 'visible' : ''}"
                             src="${rowData.image || ''}"
                             onclick="openModal(this.src)"
                             alt="Preview">
                        <label class="image-upload-btn">
                            Загрузить
                            <input type="file" class="image-input" accept="image/*" onchange="handleImageUpload(this, ${index})">
                        </label>
                        <button class="remove-image-btn ${rowData.image ? 'visible' : ''}" onclick="removeImage(this, ${index})">
                            X Удалить
                        </button>
                    </div>
                </td>
                <td class="actions-cell">
                    <button class="delete-btn" onclick="deleteRow(${index})">X</button>
                </td>
            `;

            // Track modifications
            row.querySelectorAll('td[contenteditable]').forEach(cell => {
                cell.addEventListener('input', () => {
                    modifiedRows.add(index);
                    row.classList.add('modified');
                });
            });

            return row;
        }

        /**
         * HTML escape
         */
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Add new row
         */
        function addRow() {
            const newPrototype = {
                id: null,
                num: '',
                type: '',
                method: '',
                image: ''
            };

            prototypes.push(newPrototype);
            const index = prototypes.length - 1;
            modifiedRows.add(index);

            const tbody = document.getElementById('tableBody');
            const row = createRowElement(newPrototype, index);
            row.classList.add('modified');
            tbody.appendChild(row);

            // Focus first cell
            row.querySelector('td[contenteditable]').focus();
            updateStats();
        }

        /**
         * Delete row
         */
        async function deleteRow(index) {
            const prototype = prototypes[index];

            if (prototype.id) {
                // Delete from DB
                try {
                    await apiCall('delete', { id: prototype.id });
                    showNotification('Строка удалена из базы', 'info');
                } catch (error) {
                    showNotification('Ошибка удаления: ' + error.message, 'error');
                    return;
                }
            }

            // Remove from local array
            prototypes.splice(index, 1);
            modifiedRows.delete(index);

            // Re-render
            renderTable();
        }

        /**
         * Handle image upload
         */
        function handleImageUpload(input, index) {
            const file = input.files[0];
            if (!file) return;

            if (file.size > 5 * 1024 * 1024) {
                showNotification('Файл слишком большой (макс. 5MB)', 'error');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const container = input.closest('.image-container');
                const preview = container.querySelector('.image-preview');
                const removeBtn = container.querySelector('.remove-image-btn');

                preview.src = e.target.result;
                preview.classList.add('visible');
                removeBtn.classList.add('visible');

                // Update local data
                prototypes[index].image = e.target.result;
                modifiedRows.add(index);

                const row = input.closest('tr');
                row.classList.add('modified');

                updateStats();
                showNotification('Изображение загружено', 'success');
            };
            reader.readAsDataURL(file);
        }

        /**
         * Remove image
         */
        function removeImage(btn, index) {
            const container = btn.closest('.image-container');
            const preview = container.querySelector('.image-preview');
            const input = container.querySelector('.image-input');

            preview.src = '';
            preview.classList.remove('visible');
            btn.classList.remove('visible');
            input.value = '';

            // Update local data
            prototypes[index].image = '';
            modifiedRows.add(index);

            const row = btn.closest('tr');
            row.classList.add('modified');

            updateStats();
            showNotification('Изображение удалено', 'info');
        }

        /**
         * Get current table data
         */
        function getTableData() {
            const rows = document.querySelectorAll('#tableBody tr');
            const data = [];

            rows.forEach((row, index) => {
                const cells = row.querySelectorAll('td');
                if (cells.length < 4) return;

                const imagePreview = row.querySelector('.image-preview');

                data.push({
                    id: prototypes[index]?.id || null,
                    num: cells[0].textContent.trim(),
                    type: cells[1].textContent.trim(),
                    method: cells[2].textContent.trim(),
                    image: imagePreview?.classList.contains('visible') ? imagePreview.src : ''
                });
            });

            return data;
        }

        /**
         * Save all changes to database
         */
        async function saveTable() {
            const data = getTableData();

            try {
                const result = await apiCall('save_all', { prototypes: data });
                showNotification(`Сохранено ${result.saved} записей в базу`, 'success');

                // Reload to get IDs
                await loadTable();
            } catch (error) {
                showNotification('Ошибка сохранения: ' + error.message, 'error');
            }
        }

        /**
         * Reset to default data
         */
        async function resetToDefault() {
            if (!confirm('Сбросить таблицу к исходным данным? Все изменения будут потеряны.')) {
                return;
            }

            try {
                prototypes = await apiCall('reset');
                renderTable();
                modifiedRows.clear();
                showNotification('Таблица сброшена к исходным данным', 'info');
            } catch (error) {
                showNotification('Ошибка сброса: ' + error.message, 'error');
            }
        }

        /**
         * Clear all data
         */
        async function clearTable() {
            if (!confirm('Вы уверены, что хотите очистить всю таблицу?')) {
                return;
            }

            try {
                await apiCall('save_all', { prototypes: [] });
                prototypes = [];
                renderTable();
                modifiedRows.clear();
                showNotification('Таблица очищена', 'info');
            } catch (error) {
                showNotification('Ошибка очистки: ' + error.message, 'error');
            }
        }

        /**
         * Export to JSON file
         */
        function exportJSON() {
            const data = getTableData();
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'oge_geometry_prototypes.json';
            a.click();
            URL.revokeObjectURL(url);
            showNotification('JSON экспортирован', 'success');
        }

        /**
         * Open image modal
         */
        function openModal(src) {
            if (src) {
                document.getElementById('modalImage').src = src;
                document.getElementById('imageModal').classList.add('show');
            }
        }

        /**
         * Close image modal
         */
        function closeModal() {
            document.getElementById('imageModal').classList.remove('show');
        }

        /**
         * Update statistics
         */
        function updateStats() {
            const rows = document.querySelectorAll('#tableBody tr');
            const images = document.querySelectorAll('#tableBody .image-preview.visible');

            document.getElementById('rowCount').textContent = prototypes.length;
            document.getElementById('imageCount').textContent = images.length;
        }

        /**
         * Show notification
         */
        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type} show`;

            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
            }
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                saveTable();
            }
        });

        // CSS animation for delete
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOut {
                from { opacity: 1; transform: translateX(0); }
                to { opacity: 0; transform: translateX(-20px); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
