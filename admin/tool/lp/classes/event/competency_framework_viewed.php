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
 * Competency framework viewed event.
 *
 * @package    tool_lp
 * @copyright  2016 Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lp\event;

use core\event\base;
use tool_lp\competency_framework;

defined('MOODLE_INTERNAL') || die();

/**
 * Competency framework viewed event class.
 *
 * @package    tool_lp
 * @since      Moodle 3.1
 * @copyright  2016 Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_framework_viewed extends base {

    /**
     * Convenience method to instantiate the event.
     *
     * @param competency_framework $framework The framework.
     * @return self
     */
    public static function create_from_framework(competency_framework $framework) {
        if (!$framework->get_id()) {
            throw new \coding_exception('The competency framework ID must be set.');
        }
        $event = static::create(array(
            'contextid' => $framework->get_contextid(),
            'objectid' => $framework->get_id()
        ));
        $event->add_record_snapshot(competency_framework::TABLE, $framework->to_record());
        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' viewed the competency framework with id '$this->objectid'";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventcompetencyframeworkviewed', 'tool_lp');
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/admin/tool/lp/competencies.php', array(
                                                                        'competencyframeworkid' => $this->objectid,
                                                                        'pagecontextid' => $this->contextid
                ));
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = competency_framework::TABLE;
    }

    /**
     * Get_objectid_mapping method.
     *
     * @return string the name of the restore mapping the objectid links to
     */
    public static function get_objectid_mapping() {
        return base::NOT_MAPPED;
    }

}