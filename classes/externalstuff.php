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

namespace block_modulelibrary;

use backup;
use backup_controller;
use context_course;
use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use Exception;
use invalid_parameter_exception;
use restore_controller;

/**
 * External API functions for the Module Library block.
 *
 * @package   block_modulelibrary
 * @category  external
 * @copyright 2026 Matthias Opitz <opitz@gmx.de>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class externalstuff extends external_api {
    /**
     * Validate the target course context and required capability.
     *
     * @param int $courseid
     * @return context_course
     */
    private static function validate_target_course_context(int $courseid): context_course {
        $context = context_course::instance($courseid);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);
        return $context;
    }

    /**
     * Verify that a course belongs to the configured template category.
     *
     * @param int $courseid
     * @throws invalid_parameter_exception
     */
    private static function validate_template_course(int $courseid): void {
        global $DB;

        $templatecategory = (int)get_config('block_modulelibrary', 'templatecategory');
        if (empty($templatecategory)) {
            throw new invalid_parameter_exception('Template category is not configured');
        }

        if (!$DB->record_exists('course', ['id' => $courseid, 'category' => $templatecategory])) {
            throw new invalid_parameter_exception('Template course is not in the configured template category');
        }
    }

    /**
     * Returns parameters for get_template_course_modules().
     *
     * @return external_function_parameters
     */
    public static function get_template_course_modules_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Template course id'),
            'targetcourseid' => new external_value(PARAM_INT, 'Current target course id'),
        ]);
    }

    /**
     * Returns sections and modules of a template course.
     *
     * @param int $courseid
     * @param int $targetcourseid
     * @return array
     */
    public static function get_template_course_modules(int $courseid, int $targetcourseid): array {
        global $DB;

        $params = self::validate_parameters(
            self::get_template_course_modules_parameters(),
            ['courseid' => $courseid, 'targetcourseid' => $targetcourseid]
        );

        self::validate_target_course_context($params['targetcourseid']);
        self::validate_template_course($params['courseid']);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);

        $sections = [];
        $modinfo = get_fast_modinfo($course);
        $sectioncmids = $modinfo->get_sections();

        foreach ($modinfo->get_section_info_all() as $section) {
            if ((int)$section->section === 0) {
                continue;
            }

            $cmids = $sectioncmids[$section->section] ?? [];
            if (empty($cmids)) {
                continue;
            }

            $sectionmodules = [];
            foreach ($cmids as $cmid) {
                if (!isset($modinfo->cms[$cmid])) {
                    continue;
                }
                $cm = $modinfo->cms[$cmid];
                if (!$cm->visible) {
                    continue;
                }
                $sectionmodules[] = [
                    'cmid' => $cm->id,
                    'modname' => $cm->modname,
                    'name' => format_string($cm->name, true, ['context' => $cm->context]),
                    'instance' => $cm->instance,
                ];
            }

            if (!empty($sectionmodules)) {
                $sections[] = [
                    'section' => $section->section,
                    'name' => $section->name ?: get_section_name($course, $section),
                    'modules' => $sectionmodules,
                ];
            }
        }

        return [
            'title' => format_string($course->fullname),
            'sections' => $sections,
        ];
    }

    /**
     * Returns the structure of get_template_course_modules() return values.
     *
     * @return external_single_structure
     */
    public static function get_template_course_modules_returns(): external_single_structure {
        return new external_single_structure([
            'title' => new external_value(PARAM_TEXT, 'Course title'),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'section' => new external_value(PARAM_INT, 'Section number'),
                    'name' => new external_value(PARAM_TEXT, 'Section name'),
                    'modules' => new external_multiple_structure(
                        new external_single_structure([
                            'cmid' => new external_value(PARAM_INT, 'Course module id'),
                            'modname' => new external_value(PARAM_TEXT, 'Module type'),
                            'name' => new external_value(PARAM_TEXT, 'Activity name'),
                            'instance' => new external_value(PARAM_INT, 'Instance id'),
                        ])
                    ),
                ])
            ),
        ]);
    }

    /**
     * Parameters for get_target_modules_for_copy().
     *
     * @return external_function_parameters
     */
    public static function get_target_modules_for_copy_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Returns a flat list of modules in a course.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_target_modules_for_copy(int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::get_target_modules_for_copy_parameters(), ['courseid' => $courseid]);
        self::validate_target_course_context($params['courseid']);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $modules = [];
        $modinfo = get_fast_modinfo($course);

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $modules[] = [
                'id' => $cm->id,
                'section' => $cm->sectionnum,
                'modname' => $cm->modname,
                'name' => format_string($cm->name, true, ['context' => $cm->context]),
            ];
        }
        return $modules;
    }

    /**
     * Returns structure of get_target_modules_for_copy().
     *
     * @return external_multiple_structure
     */
    public static function get_target_modules_for_copy_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Module ID'),
                'section' => new external_value(PARAM_INT, 'Section number'),
                'modname' => new external_value(PARAM_TEXT, 'Module type'),
                'name' => new external_value(PARAM_TEXT, 'Module name'),
            ])
        );
    }

    /**
     * Parameters for copy_activity().
     *
     * @return external_function_parameters
     */
    public static function copy_activity_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Source course module id'),
            'targetcourseid' => new external_value(PARAM_INT, 'Target course id'),
            'targetsection' => new external_value(PARAM_INT, 'Target section number'),
        ]);
    }

    /**
     * Copies a single activity from one course to another.
     *
     * @param int $sourcecmid
     * @param int $targetcourseid
     * @param int $targetsection
     * @return array
     */
    public static function copy_activity(int $sourcecmid, int $targetcourseid, int $targetsection): array {
        global $DB, $CFG, $USER;

        $params = self::validate_parameters(self::copy_activity_parameters(), [
            'cmid' => $sourcecmid,
            'targetcourseid' => $targetcourseid,
            'targetsection' => $targetsection,
        ]);

        self::validate_target_course_context($params['targetcourseid']);

        $cm = get_coursemodule_from_id(null, $params['cmid'], 0, false, MUST_EXIST);
        self::validate_template_course((int)$cm->course);

        $targetcourse = $DB->get_record('course', ['id' => $params['targetcourseid']], '*', MUST_EXIST);
        $section = $DB->get_record(
            'course_sections',
            ['course' => $params['targetcourseid'], 'section' => $params['targetsection']],
            '*',
            MUST_EXIST
        );

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->libdir . '/filelib.php');

        $coursecontext = context_course::instance($cm->course);
        $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        $keeptempdirectoriesonbackup = $CFG->keeptempdirectoriesonbackup;
        $roleassigned = false;

        try {
            $CFG->keeptempdirectoriesonbackup = true;

            // Temporarily grant manager on the source template course so backup can run.
            role_assign($managerrole, $USER->id, $coursecontext->id);
            $roleassigned = true;

            $bc = new backup_controller(
                backup::TYPE_1ACTIVITY,
                $cm->id,
                backup::FORMAT_MOODLE,
                backup::INTERACTIVE_NO,
                backup::MODE_GENERAL,
                $USER->id
            );

            // Force excluding any user data.
            $settings = $bc->get_plan()->get_settings();
            if (isset($settings['users'])) {
                $settings['users']->set_value(false);
            }
            if (isset($settings['userinfo'])) {
                $settings['userinfo']->set_value(false);
            }

            $backupid = $bc->get_backupid();
            $backupbasepath = $bc->get_plan()->get_basepath();

            $bc->execute_plan();
            $bc->destroy();

            $rc = new restore_controller(
                $backupid,
                $params['targetcourseid'],
                backup::INTERACTIVE_NO,
                backup::MODE_GENERAL,
                $USER->id,
                backup::TARGET_CURRENT_ADDING
            );
            $rc->set_status(backup::STATUS_AWAITING);
            $rc->execute_plan();

            $cmcontext = context_module::instance($cm->id);
            $newcmid = null;
            foreach ($rc->get_plan()->get_tasks() as $task) {
                if (is_subclass_of($task, 'restore_activity_task') && $task->get_old_contextid() == $cmcontext->id) {
                    $newcmid = $task->get_moduleid();
                    break;
                }
            }

            $rc->destroy();

            if (empty($CFG->keeptempdirectoriesonbackup)) {
                fulldelete($backupbasepath);
            }

            if ($newcmid) {
                $newcm = get_coursemodule_from_id(null, $newcmid, 0, true, MUST_EXIST);
                moveto_module($newcm, $section);

                course_module_update_calendar_events($newcm->modname, null, $newcm);

                $newcm = get_fast_modinfo($targetcourse)->get_cm($newcmid);
                $event = \core\event\course_module_created::create_from_cm($newcm);
                $event->trigger();
            }

            // Rebuild cache for the target course.
            rebuild_course_cache($params['targetcourseid'], true);

            return ['status' => true, 'message' => 'Activity restored into target course.'];
        } catch (Exception $e) {
            return ['status' => false, 'message' => 'Backup/restore failed: ' . $e->getMessage()];
        } finally {
            if ($roleassigned) {
                role_unassign($managerrole, $USER->id, $coursecontext->id);
            }
            $CFG->keeptempdirectoriesonbackup = $keeptempdirectoriesonbackup;
        }
    }

    /**
     * Returns the structure of copy_activity() return values.
     *
     * @return external_single_structure
     */
    public static function copy_activity_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status'),
            'message' => new external_value(PARAM_TEXT, 'Message'),
        ]);
    }

    /**
     * Parameters for copy_module() wrapper.
     *
     * @return external_function_parameters
     */
    public static function copy_module_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Module instance id or course module id'),
            'targetcourseid' => new external_value(PARAM_INT, 'Target course id'),
            'targetsection' => new external_value(PARAM_INT, 'Target section number'),
        ]);
    }

    /**
     * Wrapper for copying by instance id.
     *
     * @param int $instanceid
     * @param int $targetcourseid
     * @param int $targetsection
     * @return array
     */
    public static function copy_module(int $instanceid, int $targetcourseid, int $targetsection): array {
        global $DB;

        // Backward compatible: prefer exact course_modules.id match.
        $cm = $DB->get_record('course_modules', ['id' => $instanceid]);
        if (!$cm) {
            $matches = $DB->get_records('course_modules', ['instance' => $instanceid]);
            if (count($matches) !== 1) {
                throw new invalid_parameter_exception('Unable to uniquely resolve module by instance id');
            }
            $cm = reset($matches);
        }

        return self::copy_activity((int)$cm->id, $targetcourseid, $targetsection);
    }

    /**
     * Returns structure of copy_module() return values.
     *
     * @return external_single_structure
     */
    public static function copy_module_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status'),
            'message' => new external_value(PARAM_TEXT, 'Message'),
        ]);
    }

    /**
     * Parameters for get_target_course_sections() wrapper.
     *
     * @return external_function_parameters
     */
    public static function get_target_course_sections_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Target course ID'),
        ]);
    }

    /**
     * Wrapper for getting target course sections by course id.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_target_course_sections($courseid): array {
        global $DB;

        $params = self::validate_parameters(
            self::get_target_course_sections_parameters(),
            ['courseid' => $courseid]
        );

        self::validate_target_course_context($params['courseid']);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $modinfo = get_fast_modinfo($course);

        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $sections[] = [
                'sectionnum' => $section->section,
                'name' => $section->name ?: get_section_name($course, $section),
            ];
        }
        return $sections;
    }

    /**
     * Returns structure of get_target_course_sections() return values.
     *
     * @return external_multiple_structure
     */
    public static function get_target_course_sections_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'sectionnum' => new external_value(PARAM_INT, 'Section number'),
                'name' => new external_value(PARAM_TEXT, 'Section name'),
            ])
        );
    }
}
