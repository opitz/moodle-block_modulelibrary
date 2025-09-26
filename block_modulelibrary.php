<?php
defined('MOODLE_INTERNAL') || die();

class block_modulelibrary extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_modulelibrary');
    }

    public function applicable_formats() {
        return ['course-view' => true];
    }

    /**
     * Block has configuration
     */
    public function has_config() {
        return true;
    }

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

        // Render HTML (simple)
        $html = html_writer::start_tag('div', ['id' => 'block-modulelibrary']);
        $html .= html_writer::tag('label', get_string('selecttemplatecourse', 'block_modulelibrary'), ['for'=>'modulelibrary-course-select']);
        $html .= html_writer::start_tag('select', ['id'=>'modulelibrary-course-select']);
        $html .= html_writer::tag('option', get_string('choosecourse', 'block_modulelibrary'), ['value'=>'']);
        foreach ($courses as $c) {
            $html .= html_writer::tag('option', $c['fullname'], ['value'=>$c['id']]);
        }
        $html .= html_writer::end_tag('select');
        $html .= html_writer::tag('div', '<div id="modulelibrary-loading" style="display:none">' . get_string('loading', 'block_modulelibrary') . '</div><div id="modulelibrary-modules"></div><div id="modulelibrary-copy-form"></div>', []);
        $html .= html_writer::end_tag('div');

        $this->content->text = $html;

        // Provide current course id to JS.
        $PAGE->requires->js_call_amd('block_modulelibrary/modulelibrary', 'init', [
            ['currentCourseId' => $COURSE->id]
        ]);

        return $this->content;
    }
}
