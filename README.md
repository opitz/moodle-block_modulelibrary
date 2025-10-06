# Moodle Block: Module Library

A Moodle block plugin that lets you use a **template course** or template modules as a library, and **copy modules** (activities/resources) from that template into your current course.

---

## Table of Contents

- [Features](#features)
- [Requirements & Compatibility](#requirements--compatibility)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Developer Notes](#developer-notes)
- [Behat & Testing](#behat--testing)
- [Limitations & Known Issues](#limitations--known-issues)
- [License & Credits](#license--credits)

---

## Features

- Designate a **template category** and template course(s) to act as libraries of modules.
- Display modules of a template course grouped by section.
- Copy selected modules into a target course with UI (choose target section).
- Uses Moodle’s backup/restore API for copying activities.
- JavaScript / AMD front-end to dynamically load module lists.
- Mustache templates for UI rendering.

---

## Requirements & Compatibility

- Moodle 4.x (tested with Moodle 4.5+)
- PHP version compatible with your Moodle instance
- The user performing copy operations must have backup/restore capabilities (see [Developer Notes](#developer-notes))

---

## Installation

1. Clone or download this block into `blocks/modulelibrary` in your Moodle code tree.
2. Run the Moodle upgrade (Site administration → Notifications).
3. Ensure file permissions are correct (especially for backups to `moodledata`).
4. Configure the block (see next section).

---

## Configuration

After installation:

1. Go to **Site administration → Plugins → Blocks → Module Library**
2. Set the **Template category** to a category with your template courses.
3. Optionally configure other settings (if needed).

Once configured, you can add the block to courses and copy modules from templates.

---

## Usage

1. Add the **Module Library** block to a course where you want to import modules.
2. In the block, select a template course from the dropdown.
3. Sections and modules load via AJAX.
4. For each module, press **Copy**.
5. A form appears to select the target section and confirm copy.
6. The module is backed up and restored into your course, and placed in the section you chose.

---

## Developer Notes

### Capabilities & Permissions

- Copying modules uses Moodle’s **backup & restore APIs**, requiring capabilities like:
    - `moodle/backup:backupactivity`
    - `moodle/restore:restoreactivity`
    - `moodle/course:manageactivities`
- If a user is only a **teacher** but doesn’t have these capabilities in relevant contexts, copy will fail with `error/backup_user_missing_capability`.
- In testing (e.g. Behat), you may need to temporarily assign capabilities or impersonate an admin user to allow the operation to succeed.

### Caching & Cleanup

- The block sets `keeptempdirectoriesonbackup = true` temporarily so backup folders persist until cleanup.
- After restore, it cleans up the backup path if `keeptempdirectoriesonbackup` was originally false.
- Always restore `CFG->keeptempdirectoriesonbackup` to its original value.

### Templating & UI

- Uses Mustache templates (`block_content.mustache`, `modules.mustache`, `copy_form.mustache`).
- Ensure the first `<option>` in any `required` `<select>` has `value=""` (placeholder) to satisfy HTML validation.
- Avoid deprecated Bootstrap 4 classes (e.g. `form-group`, `font-weight-bold`) — use Bootstrap 5 equivalents (`mb-3`, `fw-bold`).

### AJAX / Front-end

- The block’s JS module (AMD) loads module lists and copy forms via AJAX calls to external functions.
- Must handle loading, errors, and dynamic rendering properly.

---

## Behat & Testing

To write Behat tests for this block:

- Use a `behat_hooks.php` in `blocks/modulelibrary/tests/behat/` to set up the **template category**, **template courses**, and configuration automatically.
- If the copy fails with `backup_user_missing_capability`, ensure the Behat test user is **site admin** or has required capabilities.
- Use Behat steps like:
  ```gherkin
  And I select "Template Course Name" from the "Select template course" singleselect  
  When I click on "button[data-name='Test Quiz']" "css_element"  
  And I select "Section 1" from the "target-section" singleselect  
  And I press "Confirm copy"