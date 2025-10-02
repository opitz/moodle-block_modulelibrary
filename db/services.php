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
 * Services for block_modulelibrary
 *
 * @package     block_modulelibrary
 * @copyright   2025 onwards UCL
 * @author      Matthias Opitz <m.opitz@ucl.ac.uk>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
