# Mobile Schedule Redesign Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build and publish a safe interactive mobile-first mock of the existing Evrium day/week schedule.

**Architecture:** A standalone static HTML/CSS/JavaScript artifact lives under `previews/schedule-mobile/` and uses illustrative in-memory schedule data only. A Node smoke test validates required semantics and interaction hooks; browser inspection validates rendered phone and desktop layouts.

**Tech Stack:** Semantic HTML, modern CSS, vanilla JavaScript, Node.js tests, Playwright/Chromium for screenshots if available.

---

### Task 1: Define the prototype contract

**Files:**
- Create: `previews/schedule-mobile/test.mjs`

1. Add assertions for the two view modes, teacher and day controls, schedule content, week grid, bottom sheets, and absence of production API calls.
2. Run `node previews/schedule-mobile/test.mjs` and verify it fails because `index.html` does not exist.

### Task 2: Build the mobile schedule

**Files:**
- Create: `previews/schedule-mobile/index.html`

1. Implement accessible HTML for the app bar, filters, modes, day timeline, week grid, sheets, floating action, and bottom navigation.
2. Add local illustrative data for Palomig and Руслан.
3. Implement mode, teacher, date, week settings, and edit-sheet interactions without network calls.
4. Run `node previews/schedule-mobile/test.mjs` and verify it passes.

### Task 3: Inspect and publish

**Files:**
- Deploy: `/var/www/html/preview/evrium-schedule/index.html`

1. Serve the artifact locally and capture 390×844 and 1440×1000 screenshots.
2. Fix material layout or accessibility defects in one batch and confirm once.
3. Run the design detector on `previews/schedule-mobile/index.html`.
4. Copy the tested artifact to the preview path and verify its public HTTP response.
5. Commit the prototype and plan documents.

