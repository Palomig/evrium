# Monthly Salary Bot Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a `/month` Telegram bot command and keyboard button that show salary totals for the current calendar month.

**Architecture:** Keep the existing bot command structure. Add one new handler for the month summary, then wire it into the keyboard and webhook routing. Test the new path with an isolated PHP script that stubs bot dependencies.

**Tech Stack:** PHP, Telegram Bot webhook flow, existing DB helper functions

---

### Task 1: Add the failing test

**Files:**
- Create: `zarplata/bot/tests/month_command_test.php`

**Step 1: Write the failing test**

Create a PHP script that:
- Fails if `zarplata/bot/handlers/MonthCommand.php` does not exist.
- Fails if `zarplata/bot/config.php` does not contain `📆 Месяц`.
- Fails if `zarplata/bot/webhook.php` does not route `/month`.
- If the handler exists, loads it with stubbed dependencies and asserts the rendered month summary includes period, totals, and lessons count.

**Step 2: Run test to verify it fails**

Run: `php /home/dev/evrium/zarplata/bot/tests/month_command_test.php`
Expected: FAIL because monthly handler and wiring do not exist yet.

### Task 2: Implement the monthly command

**Files:**
- Create: `zarplata/bot/handlers/MonthCommand.php`
- Modify: `zarplata/bot/config.php`
- Modify: `zarplata/bot/webhook.php`
- Modify: `zarplata/bot/README.md`

**Step 1: Write minimal implementation**

Add a new handler that:
- Checks teacher binding.
- Uses `date('Y-m-01')` as the start and `date('Y-m-d')` as the end.
- Queries `payments` grouped by `DATE(created_at)`.
- Queries total amount and lesson count for the same period.
- Sends a formatted Telegram message with month-to-date breakdown and totals.

Update keyboard/help/routing to expose `/month`.

**Step 2: Run the targeted test**

Run: `php /home/dev/evrium/zarplata/bot/tests/month_command_test.php`
Expected: PASS

### Task 3: Verify and review

**Files:**
- Review: `zarplata/bot/handlers/MonthCommand.php`
- Review: `zarplata/bot/config.php`
- Review: `zarplata/bot/webhook.php`
- Review: `zarplata/bot/README.md`

**Step 1: Run syntax checks**

Run:
- `php -l /home/dev/evrium/zarplata/bot/handlers/MonthCommand.php`
- `php -l /home/dev/evrium/zarplata/bot/config.php`
- `php -l /home/dev/evrium/zarplata/bot/webhook.php`

Expected: no syntax errors.

**Step 2: Review diff**

Run: `git -C /home/dev/evrium diff -- zarplata/bot`
Expected: only monthly bot changes plus test/docs additions.
