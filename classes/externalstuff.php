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

use context_course;
use context_module;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;

use backup_controller;
use Exception;
use restore_controller;
use backup;

/**
 * External API functions for the Module Library block.
 *
 * @package   block_modulelibrary
 * @category  external
 * @copyright 2025 UCL
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class externalstuff extends external_api {

    /**
     * Returns parameters for get_template_course_modules().
     *
     * @return external_function_parameters
     */
    public static function get_template_course_modules_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * Returns sections and modules of a template course.
     *
     * @param int $courseid
     * @return array ['title'=>string, 'sections'=>array]
     */
    public static function get_template_course_modules(int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::get_template_course_modules_parameters(),
            ['courseid' => $courseid]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);

        $sections = [];
        $modinfo = get_fast_modinfo($course);
        $allsections = $modinfo->get_section_info_all();
        $cms = $modinfo->get_cms();

        foreach ($allsections as $section) {
            // Do not show section 0 from the template.
            if ($section->sectionnum == 0) {
                continue;
            }
            $s = ['section' => $section->sectionnum, 'name' => $section->name, 'modules' => []];

            foreach ($cms as $cm) {
                if ($cm->sectionnum == $section->sectionnum) {
                    $s['modules'][] = [
                        'cmid' => $cm->id,
                        'modname' => $cm->modname,
                        'name' => $cm->name,
                        'instance' => $cm->instance,
                    ];
                }
            }

            if (!empty($s['modules'])) {
                $sections[] = $s;
            }
        }

        return [
            'title' => $course->fullname,
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

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $modules = [];
        $modinfo = get_fast_modinfo($course);
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $modules[] = [
                'id' => $cm->id,
                'section' => $cm->section,
                'modname' => $cm->modname,
                'name' => $cm->name,
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
            'targetsection' => new external_value(PARAM_INT, 'Target section (0 = append end)'),
        ]);
    }

    /**
     * Copies a single activity from one course to another.
     *
     * @param int $sourcecmid
     * @param int $targetcourseid
     * @param int $targetsection
     * @return array ['status'=>bool, 'message'=>string]
     */
    public static function copy_activity(int $sourcecmid, int $targetcourseid, int $targetsection): array {
        global $DB, $CFG, $USER;

        $params = self::validate_parameters(self::copy_activity_parameters(), [
            'cmid' => $sourcecmid,
            'targetcourseid' => $targetcourseid,
            'targetsection' => $targetsection,
        ]);

        // Basic checks.
        $cm = get_coursemodule_from_id(null, $params['cmid'], 0, false, MUST_EXIST);
        $sourcecourse = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $targetcourse = $DB->get_record('course', ['id' => $params['targetcourseid']], '*', MUST_EXIST);

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->libdir . '/filelib.php');

        try {
            // Get course from sectionid.
            $courseid = $DB->get_field('course_sections', 'course', ['id' => $sourcecmid]);
            $course = $DB->get_record('course', ['id' => $courseid]);
            $keeptempdirectoriesonbackup = $CFG->keeptempdirectoriesonbackup;
            $CFG->keeptempdirectoriesonbackup = true;

            // Grant backup/restore capabilities.
            // As the USER most likely will not have a role in the template course
            //there would be no permission to perform a backup.
            //Therefore, we will have to temporarly grant a manager role to the USER.
            $systemcontext = context_system::instance();
            $coursecontext = context_course::instance($courseid);
            $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
            // Assign the admin role in the system context.
            role_assign($managerrole, $USER->id, $coursecontext->id);

            // Backup the activity.
            $bc = new backup_controller(backup::TYPE_1ACTIVITY, $cm->id, backup::FORMAT_MOODLE,
                backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id);

            // Force exclude user data.
            $settings = $bc->get_plan()->get_settings();
            if (isset($settings['users'])) {
                $settings['users']->set_value(false);
            }
            if (isset($settings['userinfo'])) {
                $settings['userinfo']->set_value(false);
            }

            $backupid       = $bc->get_backupid();
            $backupbasepath = $bc->get_plan()->get_basepath();

            $bc->execute_plan();
            $bc->destroy();

            // Unassign the admin role again.
            role_unassign($managerrole, $USER->id, $coursecontext->id);

            // Restore the backup immediately.
            $rc = new restore_controller($backupid, $targetcourseid,
                backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id, backup::TARGET_CURRENT_ADDING);
            $rc->set_status(backup::STATUS_AWAITING);

            try {
                $rc->execute_plan();
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }

            // Now a bit hacky part follows - we try to get the cmid of the newly
            // restored copy of the module.
            $cmcontext = context_module::instance($cm->id);
            $newcmid = null;
            $tasks = $rc->get_plan()->get_tasks();
            foreach ($tasks as $task) {
                if (is_subclass_of($task, 'restore_activity_task')) {
                    if ($task->get_old_contextid() == $cmcontext->id) {
                        $newcmid = $task->get_moduleid();
                        break;
                    }
                }
            }

            $rc->destroy();

            if (empty($CFG->keeptempdirectoriesonbackup)) {
                fulldelete($backupbasepath);
            }

            if ($newcmid) {
                // Move the module to the destination section.
                $newcm = get_coursemodule_from_id(null, $newcmid, 0, true, MUST_EXIST);
                $params = ['course' => $targetcourseid, 'section' => $targetsection];
                $section = $DB->get_record('course_sections', $params);
                moveto_module($newcm, $section);

                // Update calendar events with the duplicated module.
                // The following line is to be removed in MDL-58906.
                course_module_update_calendar_events($newcm->modname, null, $newcm);

                // Trigger course module created event. We can trigger the event only if we know the newcmid.
                $newcm = get_fast_modinfo($targetcourseid)->get_cm($newcmid);
                $event = \core\event\course_module_created::create_from_cm($newcm);
                $event->trigger();
            }

            $CFG->keeptempdirectoriesonbackup = $keeptempdirectoriesonbackup;

            // Rebuild the cache for that course so the changes become effective.
            rebuild_course_cache($courseid, true);

            return ['status' => true, 'message' => 'Activity restored into target course (experimental).'];
        } catch (Exception $e) {
            return ['status' => false, 'message' => 'Backup/restore failed: ' . $e->getMessage()];
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
            'instanceid' => new external_value(PARAM_INT, 'Module instance id'),
            'targetcourseid' => new external_value(PARAM_INT, 'Target course id'),
            'targetsection' => new external_value(PARAM_INT, 'Target section (0 end)'),
        ]);
    }

    /**
     * Wrapper for copying a module by instance id.
     *
     * @param int $instanceid
     * @param int $targetcourseid
     * @param int $targetsection
     * @return array ['status'=>bool,'message'=>string]
     */
    public static function copy_module(int $instanceid, int $targetcourseid, int $targetsection) {
        global $DB;
        $cm = $DB->get_record('course_modules', ['instance' => $instanceid], '*', MUST_EXIST);
        return self::copy_activity($cm->id, $targetcourseid, $targetsection);
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
     * @return array ['status'=>bool,'message'=>string]
     */
    public static function get_target_course_sections($courseid): array {
        global $DB;
        $params = self::validate_parameters(self::get_target_course_sections_parameters(),
            ['courseid' => $courseid]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $modinfo = get_fast_modinfo($course);

        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $sections[] = [
                'sectionnum' => $section->section,
                'name' => $section->name ?: 'Section ' . $section->section,
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
