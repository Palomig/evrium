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
      default:
        return { content: [{ type: "text", text: `Unknown tool: ${name}` }], isError: true };
    }
  } catch (error) {
    return { content: [{ type: "text", text: `Error: ${error.message}` }], isError: true };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
