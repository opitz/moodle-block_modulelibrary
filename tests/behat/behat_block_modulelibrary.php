<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Settings for block_modulelibrary
 *
 * @package     block_modulelibrary
 * @copyright   2025 onwards UCL
 * @author      Matthias Opitz <m.opitz@ucl.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Behat step definitions for block_modulelibrary.
 */
class behat_block_modulelibrary extends behat_base {

    /**
     * Set the template directory.
     *
     * @Given /^the template category is set to "(?P<name_string>(?:[^"]|\\")*)"$/
     * @param string $name
     * @return void
     */
    public function set_template_category(string $name): void {
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
