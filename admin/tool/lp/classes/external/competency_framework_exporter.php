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
 * Class for exporting competency_framework data.
 *
 * @package    tool_lp
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lp\external;
defined('MOODLE_INTERNAL') || die();

use tool_lp\api;
use renderer_base;

/**
 * Class for exporting competency_framework data.
 *
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_framework_exporter extends persistent_exporter {

    /**
     * Define the name of persistent class.
     *
     * @return string
     */
    protected static function define_class() {
        return 'tool_lp\\competency_framework';
    }

    /**
     * Get other values that do not belong to the basic persisent.
     *
     * @param renderer_base $output
     * @return Array
     */
    protected function get_other_values(renderer_base $output) {
        $filters = array('competencyframeworkid' => $this->persistent->get_id());
        return array(
            'canmanage' => has_capability('tool/lp:competencymanage', $this->persistent->get_context()),
            'competenciescount' => api::count_competencies($filters),
            'contextname' => $this->persistent->get_context()->get_context_name()
        );
    }

    /**
     * Define other properties that do not belong to the basic persisent.
     *
     * @return Array
     */
    protected static function define_other_properties() {
        return array(
            'canmanage' => array(
                'type' => PARAM_BOOL
            ),
            'competenciescount' => array(
                'type' => PARAM_INT
            ),
            'contextname' => array(
                'type' => PARAM_TEXT
            )
        );
    }

}