# Monthly Salary Bot Design

**Context:** Telegram bot in `zarplata/bot` already supports salary summaries for today and current week, plus today's schedule.

**Goal:** Add a monthly salary summary for the current calendar month and expose it through the bot keyboard and slash command interface.

**Decision:** Add a separate `/month` command with its own handler `handlers/MonthCommand.php`, mirroring the existing `/week` structure to minimize regression risk. The period is fixed to the current calendar month from day `01` through the current date.

**Behavior:**
- Main keyboard gets a new `📆 Месяц` button.
- Text button `📆 Месяц` maps to `/month`.
- `/help` and the fallback hint mention the new command.
- Monthly response shows teacher, period, daily totals, lesson counts, total amount, and total lessons for the month-to-date window.

**Error Handling:**
- Unlinked teachers get the same registration warning as other salary commands.
- Empty month-to-date data returns a normal summary with a “no payments” message.

**Testing:**
- Add an isolated PHP test script that verifies monthly command wiring and summary rendering with stubbed dependencies.
