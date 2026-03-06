<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据库查看器</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 {
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .icon {
            width: 32px;
            height: 32px;
            background: #007bff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .table-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab-btn {
            padding: 12px 24px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .tab-btn:hover { border-color: #007bff; }
        .tab-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .data-panel {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .panel-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .record-count {
            color: #666;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            position: sticky;
            top: 0;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }
        tr:hover { background: #f8f9fa; }
        .id-col { width: 80px; }
        .time-col { width: 180px; }
        .points-col { width: 100px; text-align: center; }
        .points-add { color: #28a745; font-weight: bold; }
        .points-sub { color: #dc3545; font-weight: bold; }
        .type-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .type-add { background: #d4edda; color: #155724; }
        .type-sub { background: #f8d7da; color: #721c24; }
        .empty-state {
            padding: 60px;
            text-align: center;
            color: #999;
        }
        .refresh-btn {
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        .refresh-btn:hover { background: #0056b3; }
        .delete-btn {
            padding: 4px 10px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .delete-btn:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <div class="icon">DB</div>
            数据库查看器
        </h1>

        <div class="table-tabs">
            <button class="tab-btn active" onclick="loadTable('history')">积分记录</button>
            <button class="tab-btn" onclick="loadTable('students')">学生数据</button>
            <button class="tab-btn" onclick="loadTable('classes')">班级信息</button>
        </div>

        <div class="data-panel">
            <div class="panel-header">
                <span class="record-count" id="recordCount">加载中...</span>
                <button class="refresh-btn" onclick="loadTable(currentTable)">刷新</button>
            </div>
            <div id="dataContent"></div>
        </div>
    </div>

    <script>
        let currentTable = 'history';

        function loadTable(table) {
            currentTable = table;
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            if (event?.target) event.target.classList.add('active');

            document.getElementById('recordCount').textContent = '加载中...';
            document.getElementById('dataContent').innerHTML = '<div class="empty-state">加载中...</div>';

            fetch(`db_data.php?table=${table}`)
                .then(res => res.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.error) {
                            throw new Error(data.error);
                        }
                        if (!Array.isArray(data)) {
                            throw new Error('返回数据格式错误');
                        }
                        renderTable(table, data);
                    } catch (e) {
                        document.getElementById('dataContent').innerHTML =
                            '<div class="empty-state">数据解析失败：' + e.message + '<br><br>原始数据：' + text.substring(0, 200) + '</div>';
                    }
                })
                .catch(err => {
                    document.getElementById('dataContent').innerHTML =
                        '<div class="empty-state">加载失败：' + err.message + '</div>';
                });
        }

        function renderTable(table, data) {
            if (!Array.isArray(data)) {
                document.getElementById('dataContent').innerHTML =
                    '<div class="empty-state">数据格式错误：不是数组</div>';
                return;
            }

            document.getElementById('recordCount').textContent = `共 ${data.length} 条记录`;

            if (data.length === 0) {
                document.getElementById('dataContent').innerHTML =
                    '<div class="empty-state">暂无数据</div>';
                return;
            }

            let html = '<table><thead><tr>';

            if (table === 'history') {
                html += `
                    <th class="id-col">ID</th>
                    <th>学生姓名</th>
                    <th>规则</th>
                    <th class="points-col">积分</th>
                    <th>类型</th>
                    <th class="time-col">时间</th>
                </tr></thead><tbody>`;
                data.forEach(row => {
                    html += `
                        <tr>
                            <td>${row.id}</td>
                            <td>${row.studentName || '-'}</td>
                            <td>${row.rule || '-'}</td>
                            <td class="points-col ${row.points >= 0 ? 'points-add' : 'points-sub'}">
                                ${row.points > 0 ? '+' : ''}${row.points}
                            </td>
                            <td><span class="type-badge ${row.type === 'add' ? 'type-add' : 'type-sub'}">
                                ${row.type === 'add' ? '加分' : '扣分'}
                            </span></td>
                            <td>${row.time || '-'}</td>
                        </tr>`;
                });
            } else if (table === 'students') {
                html += `
                    <th class="id-col">ID</th>
                    <th>姓名</th>
                    <th>积分</th>
                    <th>可用积分</th>
                    <th>小组</th>
                </tr></thead><tbody>`;
                data.forEach(row => {
                    html += `
                        <tr>
                            <td>${row.id}</td>
                            <td>${row.name || '-'}</td>
                            <td>${row.points || 0}</td>
                            <td>${row.availablePoints || 0}</td>
                            <td>${row.group || '-'}</td>
                        </tr>`;
                });
            } else if (table === 'classes') {
                html += `
                    <th>键名</th>
                    <th>值</th>
                </tr></thead><tbody>`;
                data.forEach(row => {
                    html += `
                        <tr>
                            <td>${row.keyName || '-'}</td>
                            <td>${row.value || '-'}</td>
                        </tr>`;
                });
            }

            html += '</tbody></table>';
            document.getElementById('dataContent').innerHTML = html;
        }

        // 默认加载积分记录
        loadTable('history');
    </script>
</body>
</html>
