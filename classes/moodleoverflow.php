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

/**
 * Core class of the subplugin. This class is accessed by townsquaresupport.
 *
 * @package     townsquareexpansion_moodleoverflow
 * @copyright   2024 Tamaro Walter
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace townsquareexpansion_moodleoverflow;

defined('MOODLE_INTERNAL') || die;

use local_townsquaresupport\townsquaresupportinterface;

global $CFG;
require_once($CFG->dirroot . '/blocks/townsquare/locallib.php');

/**
 * Class that implements the townsquaresupportinterface with the function to get the events from the plugin.
 *
 * @package     townsquareexpansion_moodleoverflow
 * @copyright   2024 Tamaro Walter
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodleoverflow implements townsquaresupportinterface {

    /**
     * Function from the interface.
     * @return array
     */
    public static function get_events(): array {
        global $DB;

        $courses = townsquare_get_courses();
        $timestart = townsquare_get_timestart();

        // If moodleoverflow is not installed or not activated, return empty array.
        if (!$DB->get_record('modules', ['name' => 'forum', 'visible' => 1])) {
            return [];
        }

        // Get posts from the database.
        $moodleoverflowposts = self::get_moodleoverflowposts_from_db($courses, $timestart);

        // Filter posts by availability.
        foreach ($moodleoverflowposts as $post) {
            if (townsquare_filter_availability($post)) {
                unset($moodleoverflowposts[$post->row_num]);
            }
        }
        return $moodleoverflowposts;
    }

    private static function get_moodleoverflowposts_from_db($courses, $timestart): array {
        global $DB;
        // Prepare params for sql statement.
        list($insqlcourses, $inparamscourses) = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
        $params = ['courses' => $courses, 'timestart' => $timestart] + $inparamscourses;

        $sql = "SELECT (ROW_NUMBER() OVER (ORDER BY posts.id)) AS row_num,
                'moodleoverflow' AS modulename,
                module.id AS instanceid,
                module.anonymous AS anonymoussetting,
                'post' AS eventtype,
                cm.id AS coursemoduleid,
                cm.availability AS availability,
                module.name AS instancename,
                discuss.course AS courseid,
                discuss.userid AS discussionuserid,
                discuss.name AS discussionsubject,
                u.firstname AS postuserfirstname,
                u.lastname AS postuserlastname,
                posts.id AS postid,
                posts.discussion AS postdiscussion,
                posts.parent AS postparentid,
                posts.userid AS postuserid,
                posts.created AS timestart,
                posts.message AS postmessage
            FROM {moodleoverflow_posts} posts
            JOIN {moodleoverflow_discussions} discuss ON discuss.id = posts.discussion
            JOIN {moodleoverflow} module ON module.id = discuss.moodleoverflow
            JOIN {modules} modules ON modules.name = 'moodleoverflow'
            JOIN {user} u ON u.id = posts.userid
            JOIN {course_modules} cm ON (cm.course = module.course AND cm.module = modules.id AND cm.instance = module.id)
            WHERE discuss.course $insqlcourses
                AND posts.created > :timestart
                AND cm.visible = 1
                AND modules.visible = 1
                ORDER BY posts.created DESC;";

        return $DB->get_records_sql($sql, $params);
    }

}
