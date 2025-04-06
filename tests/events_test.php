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
 * Unit tests for the moodleoverflow townsquareexpansion
 *
 * @package   townsquareexpansion_moodleoverflow
 * @copyright 2025 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace townsquareexpansion_moodleoverflow;

use coding_exception;
use dml_exception;
use Exception;
use stdClass;

/**
 * PHPUnit tests for testing the process of event collection.
 *
 * @package     townsquareexpansion_moodleoverflow
 * @copyright   2025 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \townsquareexpansion_moodleoverflow\moodleoverflow::get_events
 */
final class events_test extends \advanced_testcase {

    /** @var stdClass  */
    private stdClass $testdata;
    private moodleoverflow $moodleoverflowevents;

    /**
     * @throws dml_exception
     */
    public function setUp(): void {
        parent::setUp();
        global $DB;
        $this->testdata = new stdClass();
        $this->moodleoverflowevents = new moodleoverflow();
        $this->resetAfterTest();

        // Check if moodleoverflow is available:
        if (!$DB->get_record('modules', ['name' => 'moodleoverflow', 'visible' => 1])) {
            $this->markTestSkipped('Moodleoverflow is not installed or not activated.');
        }
        $this->helper_course_set_uo();
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Test, if post events are sorted correctly.
     * Post should be sorted by the time they were created in descending order (newest post first).
     * @return void
     */
    public function test_sortorder(): void {
        $this->setUser($this->testdata->teacher);
        $posts = $this->moodleoverflowevents->get_events();

        // Iterate through all posts and check if the sort order is correct.
        $timestamp = 9999999999;
        $result = true;
        foreach ($posts as $post) {
            if ($timestamp < $post->timestart) {
                $result = false;
                break;
            }
            $timestamp = $post->timestart;
        }

        $this->assertTrue($result);
        $this->assertEquals(4 , count($posts));
    }

    /**
     * Test, if the post events are processed correctly if the moodleoverflow module is not installed.
     * @return void
     * @throws dml_exception
     */
    public function test_disabled_moodleoverflow(): void {
        global $DB;

        // Test case: disable moodleoverflow.
        $DB->delete_records('modules', ['name' => 'moodleoverflow']);

        // Get events from the teacher.
        $this->setUser($this->testdata->teacher);
        $posts = $this->moodleoverflowevents->get_events();

        $this->assertEquals(0, count($posts));
    }

    /**
     * Test, if the post events are processed correctly if the course disappears.
     * @return void
     * @throws dml_exception
     */
    public function test_course_deleted(): void {
        global $DB;

        // Delete the course from the database.
        $DB->delete_records('course', ['id' => $this->testdata->course1->id]);

        // Get events from the teacher.
        $this->setUser($this->testdata->teacher);
        $posts = $this->moodleoverflowevents->get_events();

        // There should be no posts from the first course.
        $result = true;
        foreach ($posts as $post) {
            if ($post->courseid == $this->testdata->course1->id) {
                $result = false;
            }
        }
        $this->assertTrue($result);
        $this->assertEquals(2, count($posts));
    }

    /**
     * Test, if the users see only posts of courses they're enrolled in.
     * @return void
     * @throws coding_exception
     */
    public function test_user_views(): void {
        // Test case 1: teacher view.
        $this->setUser($this->testdata->teacher);
        $posts = $this->moodleoverflowevents->get_events();
        $this->assertTrue($this->check_postcourses($posts, enrol_get_all_users_courses($this->testdata->teacher->id, true)));
        $this->assertEquals(4, count($posts));

        // Test case 2: first student views.
        $this->setUser($this->testdata->student1);
        $posts = $this->moodleoverflowevents->get_events();
        $this->assertTrue($this->check_postcourses($posts, enrol_get_all_users_courses($this->testdata->student1->id, true)));
        $this->assertEquals(2, count($posts));

        // Test case 3: second students view.
        $this->setUser($this->testdata->student2);
        $posts = $this->moodleoverflowevents->get_events();
        $this->assertTrue($this->check_postcourses($posts, enrol_get_all_users_courses($this->testdata->student2->id, true)));
        $this->assertEquals(2, count($posts));
    }

    /**
     * Test, if data in moodleoverflow posts is processed correctly when the moodleoverflow is anonymous.
     * @return void
     */
    public function test_anonymous(): void {
        // Set the first moodleoverflow to partially anonymous and the second to fully anonymous.
        $this->make_anonymous($this->testdata->moodleoverflow1, 1);
        $this->make_anonymous($this->testdata->moodleoverflow2, 2);

        // Get the current post events from the teacher.
        $this->setUser($this->testdata->teacher);
        $posts = $this->moodleoverflowevents->get_events();

        // Posts of the first moodleoverflow.
        $firstteacherpost = null;
        $firststudentpost = null;

        // Posts of the second moodleoverflow.
        $secondteacherpost = null;
        $secondstudentpost = null;

        // Iterate through all posts and save the posts from teacher and student.
        foreach ($posts as $post) {
            if ($post->instanceid == $this->testdata->moodleoverflow1->id) {
                if ($post->postuserid == $this->testdata->teacher->id) {
                    $firstteacherpost = $post;
                } else {
                    $firststudentpost = $post;
                }
            } else {
                if ($post->postuserid == $this->testdata->teacher->id) {
                    $secondteacherpost = $post;
                } else {
                    $secondstudentpost = $post;
                }
            }
        }

        // Test case 1: The teacherpost and studentpost are in partial anonymous mode (only questions are anonymous).
        $this->assertEquals(true, $firstteacherpost->anonymoussetting == \mod_moodleoverflow\anonymous::QUESTION_ANONYMOUS);
        $this->assertEquals(true, $firststudentpost->anonymoussetting == \mod_moodleoverflow\anonymous::QUESTION_ANONYMOUS);

        // Test case 2: The teacherpost and studentpost are in full anonymous mode (all posts are anonymous).
        $this->assertEquals(true, $secondteacherpost->anonymoussetting == \mod_moodleoverflow\anonymous::EVERYTHING_ANONYMOUS);
        $this->assertEquals(true, $secondstudentpost->anonymoussetting == \mod_moodleoverflow\anonymous::EVERYTHING_ANONYMOUS);

    }

    /**
     * Test, if posts are not shown in townsquare when a moodleoverflow is hidden.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_hidden(): void {
        global $DB;
        // Hide the first moodleoverflow.
        $cmid = get_coursemodule_from_instance('moodleoverflow', $this->testdata->moodleoverflow1->id)->id;
        $DB->update_record('course_modules', ['id' => $cmid, 'visible' => 0]);

        // Get the current post events from the teacher.
        $this->setUser($this->testdata->teacher);
        $posts = $this->moodleoverflowevents->get_events();

        // Check if the first moodleoverflow post is not in the post events.
        $result = true;
        foreach ($posts as $post) {
            if ($post->instanceid == $this->testdata->moodleoverflow1->id) {
                $result = false;
            }
        }
        $this->assertTrue($result);
    }

    // Helper functions.

    private function helper_course_set_uo(): void {
        $datagenerator = $this->getDataGenerator();
        // Create two new courses.
        $this->testdata->course1 = $datagenerator->create_course();
        $this->testdata->course2 = $datagenerator->create_course();
        // Create a teacher and enroll the teacher in both courses.
        $this->testdata->teacher = $datagenerator->create_user();
        $datagenerator->enrol_user($this->testdata->teacher->id, $this->testdata->course1->id, 'teacher');
        $datagenerator->enrol_user($this->testdata->teacher->id, $this->testdata->course2->id, 'teacher');

        // Create two students.
        $this->testdata->student1 = $datagenerator->create_user();
        $this->getDataGenerator()->enrol_user($this->testdata->student1->id, $this->testdata->course1->id, 'student');
        $this->testdata->student2 = $datagenerator->create_user();
        $this->getDataGenerator()->enrol_user($this->testdata->student2->id, $this->testdata->course2->id, 'student');

        $course1location = ['course' => $this->testdata->course1->id];
        $course2location = ['course' => $this->testdata->course2->id];
        $datagenerator = $this->getDataGenerator();
        $modoverflowgenerator = $datagenerator->get_plugin_generator('mod_moodleoverflow');

        $this->testdata->moodleoverflow1 = $datagenerator->create_module('moodleoverflow', $course1location);
        $this->testdata->mdiscussion1 = $modoverflowgenerator->post_to_forum($this->testdata->moodleoverflow1,
            $this->testdata->teacher);
        $this->testdata->answer1 = $modoverflowgenerator->reply_to_post($this->testdata->mdiscussion1[1],
            $this->testdata->student1);

        $this->testdata->moodleoverflow2 = $datagenerator->create_module('moodleoverflow', $course2location);
        $this->testdata->mdiscussion2 = $modoverflowgenerator->post_to_forum($this->testdata->moodleoverflow2,
            $this->testdata->teacher);
        $this->testdata->answer2 = $modoverflowgenerator->reply_to_post($this->testdata->mdiscussion2[1],
            $this->testdata->student2);
    }

    /**
     * Helper function to check if all posts are in the courses of the user.
     * @param array $posts
     * @param array $enrolledcourses
     * @return bool
     */
    private function check_postcourses($posts, $enrolledcourses): bool {
        foreach ($posts as $post) {
            $postcourseid = $post->courseid;

            $enrolledcoursesid = [];
            foreach ($enrolledcourses as $enrolledcourse) {
                $enrolledcoursesid[] = $enrolledcourse->id;
            }

            if (!in_array($postcourseid, $enrolledcoursesid)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Makes the existing moodleoverflow anonymous.
     * There are 2 types of anonymous moodleoverflows:
     * anonymous = 1, the topic starter is anonymous
     * anonymous = 2, all users are anonymous
     *
     * @param object $moodleoverflow The moodleoverflow that should be made anonymous.
     * @param int $anonymoussetting The type of anonymous moodleoverflow.
     * @throws Exception
     */
    private function make_anonymous($moodleoverflow, $anonymoussetting): void {
        global $DB;
        if ($anonymoussetting == 1 || $anonymoussetting == 2) {
            $moodleoverflow->anonymous = $anonymoussetting;
            $DB->update_record('moodleoverflow', $moodleoverflow);
        } else {
            throw new Exception('invalid parameter, anonymoussetting should be 1 or 2');
        }
    }
}