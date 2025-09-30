<?php
defined('MOODLE_INTERNAL') || die();

class block_modulelibrary extends block_base {

    /**
     * Initialisation
     *
     * @return void
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_modulelibrary');
    }

    /**
     * Applicable formats
     *
     * @return array true[]
     */
    public function applicable_formats(): array {
        return ['course-view' => true];
    }

    /**
     * Block has configuration
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
     * @throws \core\exception\moodle_exception
     * @throws dml_exception
     */
    public function get_content() {
        global $PAGE, $COURSE, $OUTPUT;

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
        $templatecategory = get_config('block_modulelibrary', 'templatecategory') ?: \core_course_category::get_default()->id;

        $courses = [];
        try {
            $category = \core_course_category::get($templatecategory);
            $courseobjs = $category->get_courses(['recursive' => false]);
            foreach ($courseobjs as $c) {
                $courses[] = ['id' => $c->id, 'fullname' => format_string($c->fullname)];
            }
        } catch (Exception $e) {
            $courses = [];
        }

        // Render using Mustache template.
        $data = [
            'courses' => $courses,
        ];
        $this->content->text = $OUTPUT->render_from_template('block_modulelibrary/block_content', $data);

        // Provide current course id to JS.
        $PAGE->requires->js_call_amd('block_modulelibrary/modulelibrary', 'init', [
            ['currentCourseId' => $COURSE->id]
        ]);

        return $this->content;
    }
}
