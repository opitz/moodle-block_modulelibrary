<?php
namespace block_modulelibrary;

defined('MOODLE_INTERNAL') || die();

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;

use backup_controller;
use Exception;
use restore_controller;
use backup;

class externalstuff extends external_api {

    public static function get_template_course_modules_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * Return sections and modules of a course (template).
     * Format: ['title'=>..., 'sections' => [ ['section'=>n,'name'=>..., 'modules'=>[...]], ... ]]
     */
    public static function get_template_course_modules($courseid) {
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
            'title' => $course->fullname, // just use raw string; do not call format_string()
            'sections' => $sections,
        ];
    }

    public static function get_template_course_modules_returns() {
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

    public static function get_target_modules_for_copy_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID')
        ]);
    }

    /**
     * Returns a flat list of modules in the target course including section numbers.
     * Format: [ ['id'=>cmid,'section'=>n,'modname'=>'forum','name'=>'Announcements'], ... ]
     */
    public static function get_target_modules_for_copy($courseid) {
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
                'name' => $cm->name
            ];
        }
        return $modules;
    }

    public static function get_target_modules_for_copy_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Module ID'),
                'section' => new external_value(PARAM_INT, 'Section number'),
                'modname' => new external_value(PARAM_TEXT, 'Module type'),
                'name' => new external_value(PARAM_TEXT, 'Module name')
            ])
        );
    }

    // ------------------------------------------------------------------
    // Copy/restore (experimental)
    // copy_activity: uses backup controller for a single activity and restore controller to restore into existing course.
    // NOTE: restore into existing course is tricky; this implementation disables user data and runs the restore.
    // Post-restore repositioning of the restored module into a precise section may require additional mapping steps.
    // ------------------------------------------------------------------

    public static function copy_activity_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Source course module id'),
            'targetcourseid' => new external_value(PARAM_INT, 'Target course id'),
            'targetsection' => new external_value(PARAM_INT, 'Target section (0 = append end)'),
        ]);
    }

    function install_module(int $sectionid, int $cmid, string $type):string {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->libdir . '/filelib.php');

        // Get course from sectionid.
        $courseid = $DB->get_field('course_sections', 'course', array('id' => $sectionid));
        $course = $DB->get_record('course', array('id' => $courseid));
        $keeptempdirectoriesonbackup = $CFG->keeptempdirectoriesonbackup;
        $CFG->keeptempdirectoriesonbackup = true;

        // Backup the activity.
        $bc = new backup_controller(backup::TYPE_1ACTIVITY, $cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id);

        $backupid       = $bc->get_backupid();
        $backupbasepath = $bc->get_plan()->get_basepath();

        $bc->execute_plan();
        $bc->destroy();

        // Restore the backup immediately.
        $rc = new restore_controller($backupid, $course->id,
            backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id, backup::TARGET_CURRENT_ADDING);

        // Make sure that the restore_general_groups setting is always enabled when duplicating an activity.
        $plan = $rc->get_plan();
        $groupsetting = $plan->get_setting('groups');
        if (empty($groupsetting->get_value())) {
            $groupsetting->set_value(true);
        }

        $cmcontext = context_module::instance($cmid);
        if (!$rc->execute_precheck()) {
            $precheckresults = $rc->get_precheck_results();
            if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
                if (empty($CFG->keeptempdirectoriesonbackup)) {
                    fulldelete($backupbasepath);
                }
            }
        }

        try {
            $rc->execute_plan();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        // Now a bit hacky part follows - we try to get the cmid of the newly
        // restored copy of the module.
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
            $newcm = get_coursemodule_from_id($type, $newcmid);
            $section = $DB->get_record('course_sections', array('id' => $sectionid));
            moveto_module($newcm, $section);

            // Update calendar events with the duplicated module.
            // The following line is to be removed in MDL-58906.
            course_module_update_calendar_events($newcm->modname, null, $newcm);

            // Trigger course module created event. We can trigger the event only if we know the newcmid.
            $newcm = get_fast_modinfo($course)->get_cm($newcmid);
            $event = \core\event\course_module_created::create_from_cm($newcm);
            $event->trigger();
        }

        $CFG->keeptempdirectoriesonbackup = $keeptempdirectoriesonbackup;

        // Rebuild the cache for that course so the changes become effective.
        rebuild_course_cache($courseid, true);

        return get_string('installed', 'block_modlib', ucfirst($type));
    }

    public static function copy_activity($sourcecmid, $targetcourseid, $targetsection) {
        global $DB, $CFG, $USER;

        $params = self::validate_parameters(self::copy_activity_parameters(), [
            'cmid' => $sourcecmid,
            'targetcourseid' => $targetcourseid,
            'targetsection' => $targetsection
        ]);

        // Basic checks
        $cm = get_coursemodule_from_id(null, $params['cmid'], 0, false, MUST_EXIST);
        $sourcecourse = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $targetcourse = $DB->get_record('course', ['id' => $params['targetcourseid']], '*', MUST_EXIST);

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->libdir . '/filelib.php');

        try {
            // Get course from sectionid.
            $courseid = $DB->get_field('course_sections', 'course', array('id' => $sourcecmid));
            $course = $DB->get_record('course', array('id' => $courseid));
            $keeptempdirectoriesonbackup = $CFG->keeptempdirectoriesonbackup;
            $CFG->keeptempdirectoriesonbackup = true;

            // Backup the activity.
            $bc = new backup_controller(backup::TYPE_1ACTIVITY, $cm->id, backup::FORMAT_MOODLE,
                backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id);

            $backupid       = $bc->get_backupid();
            $backupbasepath = $bc->get_plan()->get_basepath();

            $bc->execute_plan();
            $bc->destroy();

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
                $newcm = get_coursemodule_from_id(null, $newcmid, 0, true, MUST_EXIST);
//                $type = $newcm->modname;
                // Move the module to the destination section.
//                $newcm = get_coursemodule_from_id($type, $newcmid);
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

//            $targetcourse = get_course($targetcourseid);
//            move_section_to($targetcourse, $newcm->sectionnum, $targetsection);

            // Rebuild the cache for that course so the changes become effective.
            rebuild_course_cache($courseid, true);

            return ['status' => true, 'message' => 'Activity restored into target course (experimental).'];
        } catch (Exception $e) {
            return ['status' => false, 'message' => 'Backup/restore failed: ' . $e->getMessage()];
        }
    }
    public static function copy_activity0($sourcecmid, $targetcourseid, $targetsection) {
        global $DB, $CFG, $USER;

        $params = self::validate_parameters(self::copy_activity_parameters(), [
            'cmid' => $sourcecmid,
            'targetcourseid' => $targetcourseid,
            'targetsection' => $targetsection
        ]);

        // Basic checks
        $cm = get_coursemodule_from_id(null, $params['cmid'], 0, false, MUST_EXIST);
        $sourcecourse = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $targetcourse = $DB->get_record('course', ['id' => $params['targetcourseid']], '*', MUST_EXIST);

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        try {
            // Create backup controller for the single activity; produce a file.
            $bc = new backup_controller(
                backup::TYPE_1ACTIVITY,
                $cm->id,
                backup::FORMAT_MOODLE,
                backup::INTERACTIVE_NO,
                backup::MODE_IMPORT,
                $USER->id
            );

            $plan = $bc->get_plan();
            if ($plan->setting_exists('userinfo')) {
                // Exclude user info if needed
                $plan->get_setting('userinfo')->set_value(false);
            }

            // Execute backup
            $bc->execute_plan();

            // Get results (file)
//            $results = $bc->get_results();
//            $backupfile = $results['backup_destination'];
//            $backupfile = $plan->get('filename');
            // Get the backup file path for single activity
            $backupfile = $bc->get_results()['backup_destination'] ?? null;



            if (!file_exists($backupfile)) {
                throw new \moodle_exception('Backup file not found: ' . $backupfile);
            }

            // Destroy backup controller (does not delete file)
//            $bc->destroy();

            // --- Restore into target course ---
            $rc = new restore_controller(
                $backupfile,
                $targetcourse->id,
                backup::INTERACTIVE_NO,
                backup::MODE_IMPORT,
                $USER->id,
                ''
            );

            // Execute restore (precheck + plan)
            $rc->execute_precheck();
            $rc->execute_plan();

            // You can inspect mappings via $rc->get_mapping() or restore mappings table if needed.
            $rc->destroy();

            return ['status' => true, 'message' => 'Activity restored into target course (experimental).'];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Backup/restore failed: ' . $e->getMessage()];
        }
    }

    public static function copy_activity_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status'),
            'message' => new external_value(PARAM_TEXT, 'Message'),
        ]);
    }

    // Wrapper: copy_module accepts instance id and calls copy_activity internally.
    public static function copy_module_parameters() {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Module instance id'),
            'targetcourseid' => new external_value(PARAM_INT, 'Target course id'),
            'targetsection' => new external_value(PARAM_INT, 'Target section (0 end)')
        ]);
    }

    public static function copy_module($instanceid, $targetcourseid, $targetsection) {
        global $DB;
        $cm = $DB->get_record('course_modules', ['instance' => $instanceid], '*', MUST_EXIST);
        return self::copy_activity($cm->id, $targetcourseid, $targetsection);
    }

    public static function copy_module_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status'),
            'message' => new external_value(PARAM_TEXT, 'Message'),
        ]);
    }

    public static function get_target_course_sections_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Target course ID'),
        ]);
    }

    public static function get_target_course_sections($courseid) {
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

    public static function get_target_course_sections_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'sectionnum' => new external_value(PARAM_INT, 'Section number'),
                'name' => new external_value(PARAM_TEXT, 'Section name'),
            ])
        );
    }}
