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
 * Class for user_competency_course persistence.
 *
 * @package    tool_lp
 * @copyright  2016 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lp;
defined('MOODLE_INTERNAL') || die();

use context_course;
use context_user;
use lang_string;

/**
 * Class for loading/storing user_competency_course from the DB.
 *
 * @copyright  2016 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_competency_course extends persistent {

    /** Table name for user_competency persistency */
    const TABLE = 'tool_lp_user_comp_course';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'userid' => array(
                'type' => PARAM_INT,
            ),
            'courseid' => array(
                'type' => PARAM_INT
            ),
            'competencyid' => array(
                'type' => PARAM_INT,
            ),
            'proficiency' => array(
                'type' => PARAM_BOOL,
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'grade' => array(
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
        );
    }

    /**
     * Return the competency Object.
     *
     * @return competency Competency Object
     */
    public function get_competency() {
        return new competency($this->get_competencyid());
    }

    /**
     * Get the context.
     *
     * @return context The context.
     */
    public function get_context() {
        return context_user::instance($this->get_userid());
    }

    /**
     * Create a new user_competency_course object.
     *
     * Note, this is intended to be used to create a blank relation, for instance when
     * the record was not found in the database. This does not save the model.
     *
     * @param  int $userid The user ID.
     * @param  int $competencyid The competency ID.
     * @param  int $courseid The course ID.
     * @return \tool_lp\user_competency_course
     */
    public static function create_relation($userid, $competencyid, $courseid) {
        $data = new \stdClass();
        $data->userid = $userid;
        $data->competencyid = $competencyid;
        $data->courseid = $courseid;

        $relation = new user_competency_course(0, $data);
        return $relation;
    }

    /**
     * Validate the user ID.
     *
     * @param int $value The value.
     * @return true|lang_string
     */
    protected function validate_userid($value) {
        global $DB;

        if (!$DB->record_exists('user', array('id' => $value))) {
            return new lang_string('invaliduserid', 'error');
        }

        return true;
    }

    /**
     * Validate the competency ID.
     *
     * @param int $value The value.
     * @return true|lang_string
     */
    protected function validate_competencyid($value) {
        if (!competency::record_exists($value)) {
            return new lang_string('errornocompetency', 'tool_lp', $value);
        }

        return true;
    }

    /**
     * Validate course ID.
     *
     * @param int $value The course ID.
     * @return true|lang_string
     */
    protected function validate_courseid($value) {
        if (!context_course::instance($value, IGNORE_MISSING)) {
            return new lang_string('errorinvalidcourse', 'tool_lp', $value);
        }

        return true;
    }

    /**
     * Validate the proficiency.
     *
     * @param int $value The value.
     * @return true|lang_string
     */
    protected function validate_proficiency($value) {
        $grade = $this->get('grade');

        if ($grade !== null && $value === null) {
            // We must set a proficiency when we set a grade.
            return new lang_string('invaliddata', 'error');

        } else if ($grade === null && $value !== null) {
            // We must not set a proficiency when we don't set a grade.
            return new lang_string('invaliddata', 'error');
        }

        return true;
    }

    /**
     * Validate the grade.
     *
     * @param int $value The value.
     * @return true|lang_string
     */
    protected function validate_grade($value) {
        if ($value !== null) {
            if ($value <= 0) {
                return new lang_string('invalidgrade', 'tool_lp');
            }

            // TODO MDL-52243 Use a core method to validate the grade_scale item.
            // Check if grade exist in the scale item values.
            $competency = $this->get_competency();
            if (!array_key_exists($value - 1 , $competency->get_scale()->scale_items)) {
                return new lang_string('invalidgrade', 'tool_lp');
            }
        }

        return true;
    }

    /**
     * Get multiple user_competency for a user.
     *
     * @param  int $userid
     * @param  int $courseid
     * @param  array  $competenciesorids Limit search to those competencies, or competency IDs.
     * @return \tool_lp\user_competency_course[]
     */
    public static function get_multiple($userid, $courseid, array $competenciesorids = null) {
        global $DB;

        $params = array();
        $params['userid'] = $userid;
        $params['courseid'] = $courseid;
        $sql = '1 = 1';

        if (!empty($competenciesorids)) {
            $test = reset($competenciesorids);
            if (is_number($test)) {
                $ids = $competenciesorids;
            } else {
                $ids = array();
                foreach ($competenciesorids as $comp) {
                    $ids[] = $comp->get_id();
                }
            }

            list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $params += $inparams;
            $sql = "competencyid $insql";
        }

        return self::get_records_select("userid = :userid AND courseid = :courseid AND $sql", $params);
    }
}