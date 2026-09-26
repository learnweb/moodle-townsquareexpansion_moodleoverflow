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

global $CFG;
require_once($CFG->dirroot . '/blocks/townsquare/lib.php');

use coding_exception;
use core\exception\moodle_exception;
use dml_exception;
use local_townsquaresupport\townsquaresupportinterface;
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\local\models\moodleoverflow as moodleoverflow_model;
use moodle_url;

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
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_events(): array {
        global $DB;

        // If moodleoverflow is not installed or not activated, return empty array.
        if (!$module = $DB->get_record('modules', ['name' => 'moodleoverflow', 'visible' => 1])) {
            return [];
        }

        // Get important parameters directly from townsquare.
        $courses = block_townsquare_get_courses();
        $timestart = block_townsquare_get_timestart();
        $timeend = block_townsquare_get_timeend();

        // Get all moodleoverflow posts and events.
        $moodleoverflowevents = array_merge(
            self::get_moodleoverflowposts_from_db($courses, $timestart, $module->id),
            self::get_other_events_from_db($courses, $timestart, $timeend)
        );

        // Filter out events that are irrelevant for the user.
        // Irrelevant are events/posts from unavailable moodleoverflows or activity completion notifications, that are completed.
        $instances = [];
        foreach ($moodleoverflowevents as $key => $event) {
            if (
                townsquare_filter_availability($event) ||
                ($event->eventtype == 'expectcompletionon' && townsquare_filter_activitycompletions($event))
            ) {
                unset($moodleoverflowevents[$key]);
                continue;
            }

            if ($event->eventtype != 'post') {
                continue;
            }

            // Add an anonymous attribute. Load every instance only once.
            $instances[$event->instanceid] ??= moodleoverflow_model::from_id($event->instanceid);
            $event->anonymous = anonymous::is_post_anonymous(
                (object) ['userid' => $event->discussionuserid],
                $instances[$event->instanceid],
                $event->postuserid
            );

            // If the post is anonymous, make the author anonymous.
            if ($event->anonymous) {
                $event->postuserfirstname = 'anonymous';
                $event->postuserlastname = '';
            }

            // Add links.
            $event->linktopost = new moodle_url(
                '/mod/moodleoverflow/discussion.php',
                ['d' => $event->postdiscussion],
                'p' . $event->postid
            );
            $event->linktoauthor = $event->anonymous ? new moodle_url('') :
                new moodle_url('/user/view.php', ['id' => $event->postuserid]);
        }
        return $moodleoverflowevents;
    }
    /**
     * Builds the sql statement to get all moodleoverflow posts from the database.
     *
     * @param array $courses
     * @param int $timestart
     * @param int $moduleid ID of the moodleoverflow plugin in the moodle instance.
     * @return array
     * @throws dml_exception|coding_exception
     */
    private static function get_moodleoverflowposts_from_db(array $courses, int $timestart, int $moduleid): array {
        global $DB;

        // Prepare params for sql statement.
        [$insqlcourses, $params] = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
        $params += ['timestart' => $timestart, 'moduleid' => $moduleid];

        $sql = "SELECT p.id AS postid,
                   'moodleoverflow' AS modulename,
                   'post' AS eventtype,
                   m.id AS instanceid,
                   m.name AS instancename,
                   cm.id AS coursemoduleid,
                   cm.availability,
                   d.course AS courseid,
                   d.userid AS discussionuserid,
                   d.name AS discussionsubject,
                   u.firstname AS postuserfirstname,
                   u.lastname AS postuserlastname,
                   p.discussion AS postdiscussion,
                   p.parent AS postparentid,
                   p.userid AS postuserid,
                   p.created AS timestart,
                   p.message AS content,
                   p.messageformat AS postmessageformat
              FROM {moodleoverflow_posts} p
              JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
              JOIN {moodleoverflow} m ON m.id = d.moodleoverflow
              JOIN {course_modules} cm ON cm.module = :moduleid AND cm.instance = m.id
              JOIN {user} u ON u.id = p.userid
             WHERE d.course $insqlcourses
               AND p.created > :timestart
               AND p.reviewed = 1
               AND cm.visible = 1
          ORDER BY p.created DESC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Build the sql statement to get all other events related to moodleoverflow from the database.
     * @param array $courses
     * @param int $timestart
     * @param int $timeend
     * @return array
     * @throws dml_exception|coding_exception
     */
    private static function get_other_events_from_db(array $courses, int $timestart, int $timeend): array {
        global $DB;
        // Prepare params for sql statement.
        [$insqlcourses, $inparamscourses] = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);

        $params = ['timestart' => $timestart, 'timeduration' => $timestart,
                   'timeend' => $timeend, 'courses' => $courses, ] + $inparamscourses;
        // Set the sql statement.
        $sql = "SELECT e.id, e.name AS content, mo.name AS instancename, e.courseid, cm.id AS coursemoduleid,
                cm.availability AS availability, e.groupid, e.userid, e.modulename, e.instance, e.eventtype, e.timestart,
                e.timemodified, e.visible
                FROM {event} e
                JOIN {modules} m ON e.modulename = m.name
                JOIN {course_modules} cm ON (cm.course = e.courseid AND cm.module = m.id AND cm.instance = e.instance)
                JOIN {moodleoverflow} mo ON mo.id = e.instance
                WHERE (e.timestart >= :timestart OR e.timestart+e.timeduration > :timeduration)
                      AND e.timestart <= :timeend
                      AND e.courseid $insqlcourses
                      AND e.modulename = 'moodleoverflow'
                      AND m.visible = 1
                      AND (e.name NOT LIKE '" . '0' . "' AND e.eventtype NOT LIKE '" . '0' . "' )
                      AND (e.instance <> 0 AND e.visible = 1)
                ORDER BY e.timestart DESC";

        // Get all events.
        return $DB->get_records_sql($sql, $params);
    }
}
