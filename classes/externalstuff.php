<?php
namespace block_modulelibrary;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;

use backup_controller;
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

        try {
            // Create backup controller for the single activity; produce a file.
            $bc = new backup_controller(
                backup::TYPE_1ACTIVITY, // backup a single activity
                $cm->id,                // course module id
                backup::FORMAT_MOODLE,  // backup format
                backup::INTERACTIVE_NO, // non-interactive
                backup::MODE_IMPORT,    // mode
                $USER->id               // user id
            );

            // Exclude user info if needed
            $bc->get_plan()->get_setting('userinfo')->set_value(false);

            // Execute backup
            $bc->execute_plan();

            // Get results (file)
            $results = $bc->get_results();
            $backupfile = $results['backup_destination'];

            // Destroy backup controller (does not delete file)
            $bc->destroy();

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

            return ['success' => true, 'message' => 'Activity restored into target course (experimental).'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Backup/restore failed: ' . $e->getMessage()];
        }
    }

    public static function copy_activity_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
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
            'success' => new external_value(PARAM_BOOL, 'Success'),
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
