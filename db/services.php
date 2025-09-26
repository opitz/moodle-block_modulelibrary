<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_modulelibrary_get_target_course_sections' => [
        'classname'   => 'block_modulelibrary\externalstuff',
        'methodname'  => 'get_target_course_sections',
        'description' => 'Return all sections of the target course.',
        'type'        => 'read',
        'ajax'        => true,
    ],

    'block_modulelibrary_get_template_course_modules' => [
        'classname'   => 'block_modulelibrary\\externalstuff',
        'methodname'  => 'get_template_course_modules',
        'description' => 'Get list of sections & modules from a template course',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'block_modulelibrary_get_target_modules_for_copy' => [
        'classname'   => 'block_modulelibrary\\externalstuff',
        'methodname'  => 'get_target_modules_for_copy',
        'description' => 'Get modules of the current (target) course for section dropdown',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'block_modulelibrary_copy_activity' => [
        'classname'   => 'block_modulelibrary\\externalstuff',
        'methodname'  => 'copy_activity',
        'description' => 'Copy a single activity (for a template module) to target course/section',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'block_modulelibrary_copy_module' => [
        'classname'   => 'block_modulelibrary\\externalstuff',
        'methodname'  => 'copy_module',
        'description' => 'Copy a whole module instance (wrapper) to target course/section',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
