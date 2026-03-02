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

namespace block_modulelibrary;

use advanced_testcase;
use core_external\external_api;
use externallib_advanced_testcase;
use invalid_parameter_exception;
use required_capability_exception;

/**
 * PHPUnit tests for block_modulelibrary external API.
 *
 * @package     block_modulelibrary
 * @category    test
 * @copyright   2026 Matthias Opitz <opitz@gmx.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \block_modulelibrary\externalstuff
 */
final class externalstuff_test extends advanced_testcase {
    /**
     * Build fixture courses and users for external tests.
     *
     * @return array
     */
    private function create_fixture_data(): array {
        global $DB;

        $generator = $this->getDataGenerator();

        $templatecategory = $generator->create_category(['name' => 'Template Category']);
        set_config('templatecategory', $templatecategory->id, 'block_modulelibrary');

        $templatecourse = $generator->create_course([
            'fullname' => 'Template Course',
            'shortname' => 'TPL101',
            'category' => $templatecategory->id,
        ]);
        $targetcourse = $generator->create_course([
            'fullname' => 'Target Course',
            'shortname' => 'TGT101',
        ]);

        $generator->create_module('quiz', [
            'course' => $templatecourse->id,
            'section' => 1,
            'name' => 'Template Quiz 1',
        ]);

        $student = $generator->create_user();
        $teacher = $generator->create_user();

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $editingteacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);

        $generator->enrol_user($student->id, $targetcourse->id, $studentrole->id);
        $generator->enrol_user($teacher->id, $targetcourse->id, $editingteacherrole->id);

        return [
            'templatecategory' => $templatecategory,
            'templatecourse' => $templatecourse,
            'targetcourse' => $targetcourse,
            'student' => $student,
            'teacher' => $teacher,
        ];
    }

    /**
     * The template list endpoint requires manageactivities in target course.
     */
    public function test_get_template_course_modules_requires_target_capability(): void {
        $this->resetAfterTest(true);
        $fixture = $this->create_fixture_data();

        $this->setUser($fixture['student']);

        $this->expectException(required_capability_exception::class);
        externalstuff::get_template_course_modules($fixture['templatecourse']->id, $fixture['targetcourse']->id);
    }

    /**
     * The template list endpoint rejects courses outside configured template category.
     */
    public function test_get_template_course_modules_rejects_non_template_course(): void {
        $this->resetAfterTest(true);
        $fixture = $this->create_fixture_data();

        $othercategory = $this->getDataGenerator()->create_category(['name' => 'Other Category']);
        $nontemplatecourse = $this->getDataGenerator()->create_course([
            'fullname' => 'Non Template Course',
            'shortname' => 'NTPL101',
            'category' => $othercategory->id,
        ]);

        $this->setUser($fixture['teacher']);

        $this->expectException(invalid_parameter_exception::class);
        externalstuff::get_template_course_modules($nontemplatecourse->id, $fixture['targetcourse']->id);
    }

    /**
     * The template list endpoint returns sections/modules for authorized users.
     */
    public function test_get_template_course_modules_returns_sections_and_modules(): void {
        $this->resetAfterTest(true);
        $fixture = $this->create_fixture_data();

        $this->setUser($fixture['teacher']);

        $result = externalstuff::get_template_course_modules($fixture['templatecourse']->id, $fixture['targetcourse']->id);
        $result = external_api::clean_returnvalue(externalstuff::get_template_course_modules_returns(), $result);

        $this->assertSame('Template Course', $result['title']);
        $this->assertCount(1, $result['sections']);
        $this->assertSame(1, $result['sections'][0]['section']);
        $this->assertCount(1, $result['sections'][0]['modules']);
        $this->assertSame('quiz', $result['sections'][0]['modules'][0]['modname']);
        $this->assertSame('Template Quiz 1', $result['sections'][0]['modules'][0]['name']);
    }

    /**
     * The target section list endpoint requires manageactivities in target course.
     */
    public function test_get_target_course_sections_requires_capability(): void {
        $this->resetAfterTest(true);
        $fixture = $this->create_fixture_data();

        $this->setUser($fixture['student']);

        $this->expectException(required_capability_exception::class);
        externalstuff::get_target_course_sections($fixture['targetcourse']->id);
    }
}
