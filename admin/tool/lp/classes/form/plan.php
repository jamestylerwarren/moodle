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
 * This file contains the form add/update a learning plan.
 *
 * @package   tool_lp
 * @copyright 2015 David Monllao
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lp\form;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

use moodleform;
use tool_lp\api;

require_once($CFG->libdir.'/formslib.php');

/**
 * Learning plan form.
 *
 * @package   tool_lp
 * @copyright 2015 David Monllao
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plan extends moodleform {

    /**
     * Define the form - called by parent constructor
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', 0);

        $mform->addElement('hidden', 'userid', $this->_customdata['userid']);
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('planname', 'tool_lp'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('editor', 'description', get_string('plandescription', 'tool_lp'), array('rows' => 4));
        $mform->setType('description', PARAM_TEXT);

        $templates = $this->get_template_options();
        if ($templates) {
            $mform->addElement('select', 'templateid', get_string('plantemplate', 'tool_lp'), $templates);
            $mform->addHelpButton('templateid', 'plantemplate', 'tool_lp');
        }

        $mform->addElement('date_selector', 'duedate', get_string('duedate', 'tool_lp'));
        $mform->addHelpButton('duedate', 'duedate', 'tool_lp');

        $this->add_action_buttons(true, get_string('savechanges', 'tool_lp'));

        if (!empty($this->_customdata['id'])) {
            if (!$this->is_submitted()) {
                $plan = api::read_plan($this->_customdata['id']);
                $record = $plan->to_record();
                $record->description = array('text' => $record->description, 'format' => $record->descriptionformat);
                $this->set_data($record);
            }
        }

    }

    /**
     * Get the template select options from the templates list.
     *
     * @return array|false
     */
    protected function get_template_options() {
        if (empty($this->_customdata['templates'])) {
            return false;
        }

        $options = array('' => get_string('choosedots'));
        foreach ($this->_customdata['templates'] as $template) {
            $options[$template->get_id()] = $template->get_shortname();
        }
        return $options;
    }
}