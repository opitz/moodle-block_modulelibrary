<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Block: Module Library.
 *
 * @package     block_modulelibrary
 * @copyright   2025 onwards UCL
 * @author      Matthias Opitz <m.opitz@ucl.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class block_modulelibrary
 *
 * Provides a block for managing and copying template modules.
 */
class block_modulelibrary extends block_base {

    /**
     * Initialise the block title.
     *
     * @return void
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_modulelibrary');
    }

    /**
     * Define applicable formats.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return ['course-view' => true];
    }

    /**
     * Whether this block has a global configuration page.
     *
     * @return bool
     */
    public function has_config(): bool {
        return true;
    }

    /**
     * Get the block content.
     *
     * @return stdClass|null
     */
    public function get_content() {
        global $COURSE, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();

        // Only show when editing is on.
        if (!$this->page->user_is_editing()) {
            $this->content->text = '';
            return $this->content;
        }

        // Template category (setting) or default.
        $templatecategory = get_config('block_modulelibrary', 'templatecategory')
            ?: \core_course_category::get_default()->id;

        $courses = [];
        try {
            $category = \core_course_category::get($templatecategory);
            $courseobjs = $category->get_courses(['recursive' => false]);
            foreach ($courseobjs as $c) {
                $courses[] = [
                    'id' => $c->id,
                    'fullname' => format_string($c->fullname),
                ];
            }
        } catch (Exception $e) {
            $courses = [];
        }

        // Render using Mustache template.
        $data = [
            'courses' => $courses,
        ];
        $this->content->text = $OUTPUT->render_from_template(
            'block_modulelibrary/block_content',
            $data
        );

        // Provide current course id to JS.
        $this->page->requires->js_call_amd(
            'block_modulelibrary/modulelibrary',
            'init',
            [['currentCourseId' => $COURSE->id]]
        );

        return $this->content;
    }
}
