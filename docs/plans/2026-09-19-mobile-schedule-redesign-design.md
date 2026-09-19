# Mobile Schedule Redesign

## Purpose

Create a safe, standalone prototype of Evrium's tutoring schedule for its primary phone use case. The prototype must preserve the existing mental model and functionality while making the interface faster to scan and easier to operate with one hand.

## Preserved behavior

- Day and week modes.
- One active teacher at a time.
- Day switching and swipe navigation.
- Editable lesson title and student chips.
- Adding a lesson or student.
- Temporary-student state.
- Week settings for morning hours and visible days.
- Existing dark Evrium identity and teal teacher color.

## Screen design

The top app bar identifies the surface as “Расписание” and keeps only contextual actions. Below it, teacher selection and the `День / Неделя` segmented control form one compact control deck.

Day mode uses a seven-day date strip and a vertical timeline. Lessons are aligned to prominent times, with the title and students grouped into a single readable surface. Empty time is communicated by spacing rather than full-size blank cards. A floating add button is reachable by the right thumb.

Week mode keeps time frozen on the left and days frozen at the top. Three days are visible at phone width; the remaining days are reached by horizontal scrolling. Lesson cells prioritize class name and student names. Empty cells remain tappable but visually quiet.

Editing opens as a bottom sheet. The prototype demonstrates the interaction locally and never calls production APIs.

## Visual direction

Operate-mode interface for repeated use in mixed indoor lighting: deep graphite surfaces, restrained teal and violet teacher accents, high-contrast text, compact but comfortable touch targets, and no decorative effects that compete with the schedule. Typography uses a workhorse sans stack and tabular figures for times.

## Responsive behavior

The phone layout is canonical. Wider screens center the app in a phone-like working column for day mode, while week mode can expand to show more days. The bottom navigation remains visible to reflect the production mobile shell.

## Verification

- Structural tests verify both modes, teacher/day controls, week grid, edit sheet, settings sheet, and local-only behavior.
- Browser checks cover 390×844 and 1440×1000.
- Interaction checks cover mode changes, teacher/day changes, lesson editing, adding a lesson, and settings.

