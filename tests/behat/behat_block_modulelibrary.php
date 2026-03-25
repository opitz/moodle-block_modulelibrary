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
 * Behat steps for block_modulelibrary.
 *
 * @package    block_modulelibrary
 * @copyright  2026 Bob OpenClaw
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use Behat\Gherkin\Node\TableNode;

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat steps for block_modulelibrary.
 */
class behat_block_modulelibrary extends behat_base {
    /**
     * Set the block template category config by category name.
     *
     * @Given /^the modulelibrary template category is set to "(?P<categoryname>[^"]*)"$/
     * @param string $categoryname
     * @return void
     */
    public function the_modulelibrary_template_category_is_set_to(string $categoryname): void {
        global $DB;

        $categoryid = $DB->get_field('course_categories', 'id', ['name' => $categoryname], MUST_EXIST);
        set_config('templatecategory', $categoryid, 'block_modulelibrary');
    }

    /**
     * Confirm an activity is shown in a named course section.
     *
     * @Then /^I should see activity "(?P<activityname>[^"]*)" in section "(?P<sectionname>[^"]*)"$/
     * @param string $activityname
     * @param string $sectionname
     * @return void
     */
    public function i_should_see_activity_in_section(string $activityname, string $sectionname): void {
        $sectionxpath = "//li[contains(@class, 'section')]//*[contains(normalize-space(.), " .
            $this->escape($sectionname) . ")]";
        $activityxpath = "//li[contains(@class, 'section')][.//*[contains(normalize-space(.), " .
            $this->escape($sectionname) . ")]]//*[contains(normalize-space(.), " .
            $this->escape($activityname) . ")]";

        $this->find('xpath', $sectionxpath);
        $this->find('xpath', $activityxpath);
    }
}
