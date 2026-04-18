#!/usr/bin/env node
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";

const API_BASE = process.env.EVRIUM_API_URL || "https://xn--h1abaiw3e.xn--p1ai"; // эвриум.рф (punycode)
const SECRET   = process.env.EVRIUM_MCP_SECRET || "";

async function fetchAPI(action, body, method = "GET") {
  const url = `${API_BASE}/zarplata/api/mcp.php?action=${encodeURIComponent(action)}`;
  const opts = {
    method,
    headers: {
      "Content-Type": "application/json",
      "X-Mcp-Secret": SECRET,
    },
  };
  if (body && method !== "GET") opts.body = JSON.stringify(body);

  const resp = await fetch(url, opts);
  const text = await resp.text();
  try {
    return JSON.parse(text);
  } catch {
    return { success: false, status: resp.status, raw: text.slice(0, 500) };
  }
}

const server = new Server(
  { name: "evrium-db", version: "1.0.0" },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [
    {
      name: "list_tables",
      description: "Список всех таблиц БД Evrium/zarplata (cw95865_admin) с количеством строк",
      inputSchema: { type: "object", properties: {} },
    },
    {
      name: "query",
      description:
        "Выполнить read-only SQL запрос к БД Evrium (SELECT/SHOW/DESCRIBE/EXPLAIN). Мутации запрещены.",
      inputSchema: {
        type: "object",
        properties: {
          sql:   { type: "string", description: "SQL запрос (только SELECT/SHOW/DESCRIBE/EXPLAIN)" },
          limit: { type: "number", description: "Максимум строк (по умолчанию 100, макс 1000)" },
        },
        required: ["sql"],
      },
    },
    {
      name: "describe_table",
      description: "Показать структуру таблицы (колонки, типы, ключи) в БД Evrium",
      inputSchema: {
        type: "object",
        properties: {
          table: { type: "string", description: "Имя таблицы" },
        },
        required: ["table"],
      },
    },
    {
      name: "tail_cron_log",
      description:
        "Прочитать хвост одного из серверных лог-файлов zarplata. Доступные файлы: cron_debug (лог крона посещаемости), bot_errors, php_error.",
      inputSchema: {
        type: "object",
        properties: {
          file:  { type: "string", description: "Имя файла: cron_debug | bot_errors | php_error (по умолчанию cron_debug)" },
          lines: { type: "number", description: "Сколько последних строк вернуть (1..500, по умолчанию 100)" },
        },
      },
    },
    {
      name: "send_test_push",
      description:
        "Отправить тестовое Web Push уведомление на подписки учителя (или все, если teacher_id не задан). Возвращает HTTP-статус от FCM/Mozilla Push для каждой подписки — полезно для диагностики доставки.",
      inputSchema: {
        type: "object",
        properties: {
          teacher_id: { type: "number", description: "ID учителя из таблицы teachers. Если не задан — все активные подписки." },
          title:      { type: "string", description: "Заголовок уведомления" },
          body:       { type: "string", description: "Текст уведомления" },
        },
      },
    },
  ],
}));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  try {
    switch (name) {
      case "list_tables": {
        const data = await fetchAPI("tables", null, "GET");
        return { content: [{ type: "text", text: JSON.stringify(data, null, 2) }] };
      }
      case "query": {
        const data = await fetchAPI("query", { sql: args.sql, limit: args.limit || 100 }, "POST");
        return { content: [{ type: "text", text: JSON.stringify(data, null, 2) }] };
      }
      case "describe_table": {
        const data = await fetchAPI("describe", { table: args.table }, "POST");
        return { content: [{ type: "text", text: JSON.stringify(data, null, 2) }] };
      }
      case "tail_cron_log": {
        const file  = args.file  || "cron_debug";
        const lines = args.lines || 100;
        const url = `${API_BASE}/zarplata/api/mcp.php?action=log&file=${encodeURIComponent(file)}&lines=${encodeURIComponent(lines)}`;
        const resp = await fetch(url, { headers: { "X-Mcp-Secret": SECRET } });
        const data = await resp.json().catch(() => ({ success: false, status: resp.status }));
        return { content: [{ type: "text", text: JSON.stringify(data, null, 2) }] };
      }
      case "send_test_push": {
        const body = {};
        if (typeof args.teacher_id === "number") body.teacher_id = args.teacher_id;
        if (args.title) body.title = args.title;
        if (args.body)  body.body  = args.body;
        const data = await fetchAPI("push_test", body, "POST");
        return { content: [{ type: "text", text: JSON.stringify(data, null, 2) }] };
      }
      default:
        return { content: [{ type: "text", text: `Unknown tool: ${name}` }], isError: true };
    }
  } catch (error) {
    return { content: [{ type: "text", text: `Error: ${error.message}` }], isError: true };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
