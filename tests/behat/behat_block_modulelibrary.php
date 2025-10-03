<?php
// This file is part of Moodle - https://moodle.org/.

use Behat\Behat\Context\Context;

/**
 * Behat step definitions for block_modulelibrary.
 */
class behat_block_modulelibrary extends behat_base {

    /**
     * @Given /^the template category is set to "(?P<name_string>(?:[^"]|\\")*)"$/
     * @param string $name
     */
    public function set_template_category($name) {
        global $DB;

        $category = $DB->get_record('course_categories', ['name' => $name]);
        if (!$category) {
            $categoryid = \core_course_category::create(['name' => $name])->id;
        } else {
            $categoryid = $category->id;
        }

        set_config('templatecategory', $categoryid, 'block_modulelibrary');
    }
}
