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
 * Abstract class for tool_lp objects saved to the DB.
 *
 * @package    tool_lp
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lp;

use coding_exception;
use invalid_parameter_exception;
use lang_string;
use stdClass;

/**
 * Abstract class for tool_lp objects saved to the DB.
 *
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class persistent {

    /** The table name. */
    const TABLE = null;

    /** The model data. */
    private $data = array();

    /** @var boolean The list of validation errors. */
    private $errors = array();

    /** @var boolean If the data was already validated. */
    private $validated = false;

    /**
     * Create an instance of this class.
     *
     * @param int $id If set, this is the id of an existing record, used to load the data.
     * @param stdClass $record If set will be passed to {@link self::from_record()}.
     */
    public function __construct($id = 0, stdClass $record = null) {
        if ($id > 0) {
            $this->set('id', $id);
            $this->read();
        }
        if (!empty($record)) {
            $this->from_record($record);
        }
    }

    /**
     * Magic method to capture getters and setters.
     *
     * @param  string $name Callee.
     * @param  array $arguments List of arguments.
     * @return mixed
     */
    final public function __call($method, $arguments) {
        if (strpos($method, 'get_') === 0) {
            return $this->get(substr($method, 4));
        } else if (strpos($method, 'set_') === 0) {
            return $this->set(substr($method, 4), $arguments[0]);
        }
        throw new coding_exception('Unexpected method call');
    }

    /**
     * Data getter.
     *
     * This is the main getter for all the properties. Developers can implement their own getters
     * but they should be calling {@link self::get()} in order to retrieve the value. Essentially
     * the getters defined by the developers would only ever be used as helper methods and will not
     * be called internally at this stage. In other words, do not expect {@link self::to_record()} or
     * {@link self::from_record()} to use them.
     *
     * This is protected because we wouldn't want the developers to get into the habit of
     * using $persistent->get('property_name'), the lengthy getters must be used.
     *
     * @param  string $property The property name.
     * @return mixed
     */
    final protected function get($property) {
        if (!static::has_property($property)) {
            throw new coding_exception('Unexpected property \'' . s($property) .'\' requested.');
        }
        if (!array_key_exists($property, $this->data) && !static::is_property_required($property)) {
            $this->set($property, static::get_property_default_value($property));
        }
        return isset($this->data[$property]) ? $this->data[$property] : null;
    }

    /**
     * Data setter.
     *
     * This is the main setter for all the properties. Developers can implement their own setters
     * but they should always be calling {@link self::set()} in order to set the value. Essentially
     * the setters defined by the developers are helper methods and will not be called internally
     * at this stage. In other words do not expect {@link self::to_record()} or
     * {@link self::from_record()} to use them.
     *
     * This is protected because we wouldn't want the developers to get into the habit of
     * using $persistent->set('property_name', ''), the lengthy setters must be used.
     *
     * @param  string $property The property name.
     * @return mixed
     */
    final protected function set($property, $value) {
        if (!static::has_property($property)) {
            throw new coding_exception('Unexpected property \'' . s($property) .'\' requested.');
        }
        if (!array_key_exists($property, $this->data) || $this->data[$property] != $value) {
            // If the value is changing, we invalidate the model.
            $this->validated = false;
        }
        $this->data[$property] = $value;
    }

    /**
     * Return the custom definition of the properties of this model.
     *
     * Each property MUST be listed here.
     *
     * Example:
     *
     * array(
     *     'property_name' => array(
     *         'default' => 'Default value',        // When not set, the property is considered as required.
     *         'message' => new lang_string(...),   // Defaults to invalid data error message.
     *         'null' => NULL_ALLOWED,              // Defaults to NULL_NOT_ALLOWED. Takes NULL_NOW_ALLOWED or NULL_ALLOWED.
     *         'type' => PARAM_TYPE,                // Mandatory.
     *         'choices' => array(1, 2, 3)          // An array of accepted values.
     *     )
     * )
     *
     * @return array Where keys are the property names.
     */
    protected static function define_properties() {
        return array();
    }

    /**
     * Get the properties definition of this model..
     *
     * @return array
     */
    final public static function properties_definition() {
        global $CFG;

        static $def = null;
        if ($def !== null) {
            return $def;
        }

        $def = static::define_properties();
        $def['id'] = array(
            'default' => 0,
            'type' => PARAM_INT,
        );
        $def['timecreated'] = array(
            'default' => 0,
            'type' => PARAM_INT,
        );
        $def['timemodified'] = array(
            'default' => 0,
            'type' => PARAM_INT
        );
        $def['usermodified'] = array(
            'default' => 0,
            'type' => PARAM_INT
        );

        // Warn the developers when they are doing something wrong.
        if ($CFG->debugdeveloper) {

            // List of reserved property names. Mostly because we have methods (getters/setters) which would confict with them.
            // Think about backwards compability before adding new ones here!
            $reserved = array('errors', 'records', 'records_select', 'property_default_value', 'property_error_message');

            foreach ($def as $property => $definition) {
                if (!array_key_exists('type', $definition)) {
                    throw new coding_exception('Missing type for: ' . $property);

                } else if (isset($definition['message']) && !($definition['message'] instanceof lang_string)) {
                    throw new coding_exception('Invalid error message for: ' . $property);

                } else if (in_array($property, $reserved)) {
                    throw new coding_exception('This property cannot be defined: ' . $property);

                }
            }
        }

        return $def;
    }

    /**
     * Gets the default value for a property.
     *
     * This assumes that the property exists.
     *
     * @param string $property The property name.
     * @return mixed
     */
    final protected static function get_property_default_value($property) {
        $properties = static::properties_definition();
        if (!isset($properties[$property]['default'])) {
            return null;
        }
        return $properties[$property]['default'];
    }

    /**
     * Gets the error message for a property.
     *
     * This assumes that the property exists.
     *
     * @param string $property The property name.
     * @return lang_string
     */
    final protected static function get_property_error_message($property) {
        $properties = static::properties_definition();
        if (!isset($properties[$property]['message'])) {
            return new lang_string('invaliddata', 'error');
        }
        return $properties[$property]['message'];
    }

    /**
     * Returns whether or not a property was defined.
     *
     * @param  string $property The property name.
     * @return boolean
     */
    final public static function has_property($property) {
        $properties = static::properties_definition();
        return isset($properties[$property]);
    }

    /**
     * Returns whether or not a property is required.
     *
     * By definition a property with a default value is not required.
     *
     * @param  string $property The property name.
     * @return boolean
     */
    final public static function is_property_required($property) {
        $properties = static::properties_definition();
        return !array_key_exists('default', $properties[$property]);
    }

    /**
     * Populate this class with data from a DB record.
     *
     * Note that this does not use any custom setter because the data here is intended to
     * represent what is stored in the database.
     *
     * @param \stdClass $record A DB record.
     * @return persistent
     */
    final public function from_record(stdClass $record) {
        $record = (array) $record;
        foreach ($record as $property => $value) {
            $this->set($property, $value);
        }
        return $this;
    }

    /**
     * Create a DB record from this class.
     *
     * Note that this does not use any custom getter because the data here is intended to
     * represent what is stored in the database.
     *
     * @return \stdClass
     */
    final public function to_record() {
        $data = new stdClass();
        $properties = static::properties_definition();
        foreach ($properties as $property => $definition) {
            $data->$property = $this->get($property);
        }
        return $data;
    }

    /**
     * Load the data from the DB.
     *
     * @return persistent
     */
    final public function read() {
        global $DB;

        if ($this->get_id() <= 0) {
            throw new coding_exception('id is required to load');
        }
        $record = $DB->get_record(static::TABLE, array('id' => $this->get_id()), '*', MUST_EXIST);
        $this->from_record($record);

        // Validate the data as it comes from the database.
        $this->validated = true;

        return $this;
    }

    /**
     * Hook to execute before a create.
     *
     * Please note that at this stage the data has already been validated and therefore
     * any new data being set will not be validated before it is sent to the database.
     *
     * This is only intended to be used by child classes, do not put any logic here!
     *
     * @return void
     */
    protected function before_create() {
    }

    /**
     * Insert a record in the DB.
     *
     * @return persistent
     */
    final public function create() {
        global $DB, $USER;

        if (!$this->is_valid()) {
            throw new invalid_persistent_exception();
        }

        // Before create hook.
        $this->before_create();

        // We can safely set those values bypassing the validation because we know what we're doing.
        $now = time();
        $this->set('id', 0);
        $this->set('timecreated', $now);
        $this->set('timemodified', $now);
        $this->set('usermodified', $USER->id);

        $record = $this->to_record();

        $id = $DB->insert_record(static::TABLE, $record);
        $this->set('id', $id);

        // We ensure that this is flagged as validated.
        $this->validated = true;

        // After create hook.
        $this->after_create();

        return $this;
    }

    /**
     * Hook to execute after a create.
     *
     * This is only intended to be used by child classes, do not put any logic here!
     *
     * @return void
     */
    protected function after_create() {
    }

    /**
     * Hook to execute before an update.
     *
     * Please note that at this stage the data has already been validated and therefore
     * any new data being set will not be validated before it is sent to the database.
     *
     * This is only intended to be used by child classes, do not put any logic here!
     *
     * @return void
     */
    protected function before_update() {
    }

    /**
     * Update the existing record in the DB.
     *
     * @return bool True on success.
     */
    final public function update() {
        global $DB, $USER;

        if ($this->get_id() <= 0) {
            throw new coding_exception('id is required to update');
        } else if (!$this->is_valid()) {
            throw new invalid_persistent_exception();
        }

        // Before update hook.
        $this->before_update();

        // We can safely set those values after the validation because we know what we're doing.
        $this->set('timemodified', time());
        $this->set('usermodified', $USER->id);

        $record = $this->to_record();
        unset($record->timecreated);
        $record = (array) $record;

        // Save the record.
        $result = $DB->update_record(static::TABLE, $record);

        // We ensure that this is flagged as validated.
        $this->validated = true;

        // After update hook.
        $this->after_update($result);

        return $result;
    }

    /**
     * Hook to execute after an update.
     *
     * This is only intended to be used by child classes, do not put any logic here!
     *
     * @param bool $result Whether or not the update was successful.
     * @return void
     */
    protected function after_update($result) {
    }

    /**
     * Hook to execute before a delete.
     *
     * This is only intended to be used by child classes, do not put any logic here!
     *
     * @return void
     */
    protected function before_delete() {
    }

    /**
     * Delete an entry from the database.
     *
     * @return bool True on success.
     */
    final public function delete() {
        global $DB;

        if ($this->get_id() <= 0) {
            throw new coding_exception('id is required to delete');
        }

        // Hook before delete.
        $this->before_delete();

        $result = $DB->delete_records(static::TABLE, array('id' => $this->get_id()));

        // Hook after delete.
        $this->after_delete($result);

        // Reset the ID to avoid any confusion, this also invalidates the model's data.
        if ($result) {
            $this->set('id', 0);
        }

        return $result;
    }

    /**
     * Hook to execute after a delete.
     *
     * This is only intended to be used by child classes, do not put any logic here!
     *
     * @param bool $result Whether or not the delete was successful.
     * @return void
     */
    protected function after_delete($result) {
    }

    /**
     * Hook to execute before the validation.
     *
     * This hook will not affect the validation results in any way but is useful to
     * internally set properties which will need to be validated.
     *
     * This is only intended to be used by child classes, do not put any logic here!
     *
     * @return void
     */
    protected function before_validate() {
    }

    /**
     * Validates the data.
     *
     * Developers can implement addition validation by defining a method as follows. Note that
     * the method MUST return a lang_string() when there is an error, and true when the data is valid.
     *
     * public function validate_propertyname($value) {
     *     if ($value !== 'My expected value') {
     *         return new lang_string('invaliddata', 'error');
     *     }
     *     return true
     * }
     *
     * @return array|true Returns true when the validation passed, or an array of properties with errors.
     */
    final public function validate() {

        // If this object has not been validated yet.
        if ($this->validated !== true) {

            // Before validate hook.
            $this->before_validate();

            $errors = array();
            $properties = static::properties_definition();
            foreach ($properties as $property => $definition) {

                // Get the data, bypassing the potential custom getter which could alter the data.
                $value = $this->get($property);

                // Check if the property is required.
                if ($value === null && static::is_property_required($property)) {
                    $errors[$property] = new lang_string('requiredelement', 'form');
                    continue;
                }

                // Check that type of value is respected.
                try {
                    if ($definition['type'] === PARAM_BOOL && $value === false) {
                        // Validate_param() does not like false with PARAM_BOOL, better to convert it to int.
                        $value = 0;
                    }
                    $allownull = isset($definition['null']) ? $definition['null'] : NULL_NOT_ALLOWED;
                    validate_param($value, $definition['type'], $allownull);
                } catch (invalid_parameter_exception $e) {
                    $errors[$property] = static::get_property_error_message($property);
                    continue;
                }

                // Check that the value is part of a list of allowed values.
                if (isset($definition['choices']) && !in_array($value, $definition['choices'])) {
                    $errors[$property] = static::get_property_error_message($property);
                    continue;
                }

                // Call custom validation method.
                $method = 'validate_' . $property;
                if (method_exists($this, $method)) {
                    $valid = $this->{$method}($value);
                    if ($valid !== true) {
                        if (!($valid instanceof lang_string)) {
                            throw new coding_exception('Unexpected error message.');
                        }
                        $errors[$property] = $valid;
                        continue;
                    }
                }
            }

            $this->validated = true;
            $this->errors = $errors;
        }

        return empty($this->errors) ? true : $this->errors;
    }

    /**
     * Returns whether or not the model is valid.
     *
     * @return boolean True when it is.
     */
    final public function is_valid() {
        return $this->validate() === true;
    }

    /**
     * Returns the validation errors.
     *
     * @return array
     */
    final public function get_errors() {
        $this->validate();
        return $this->errors;
    }

    /**
     * Load a list of records.
     *
     * @param array $filters Filters to apply.
     * @param string $sort Field to sort by.
     * @param string $order Sort order.
     * @param int $skip Limitstart.
     * @param int $limit Number of rows to return.
     *
     * @return persistent[]
     */
    public static function get_records($filters = array(), $sort = '', $order = 'ASC', $skip = 0, $limit = 0) {
        global $DB;

        $orderby = '';
        if (!empty($sort)) {
            $orderby = $sort . ' ' . $order;
        }

        $records = $DB->get_records(static::TABLE, $filters, $orderby, '*', $skip, $limit);
        $instances = array();

        foreach ($records as $record) {
            $newrecord = new static(0, $record);
            array_push($instances, $newrecord);
        }
        return $instances;
    }

    /**
     * Load a list of records based on a select query.
     *
     * @param string $select
     * @param array $params
     * @param string $sort
     * @param string $fields
     * @param int $limitfrom
     * @param int $limitnum
     * @return \tool_lp\plan[]
     */
    public static function get_records_select($select, $params = null, $sort = '', $fields = '*', $limitfrom = 0, $limitnum = 0) {
        global $DB;

        $records = $DB->get_records_select(static::TABLE, $select, $params, $sort, $fields, $limitfrom, $limitnum);

        // We return class instances.
        $instances = array();
        foreach ($records as $record) {
            array_push($instances, new static(0, $record));
        }

        return $instances;

    }

    /**
     * Count a list of records.
     *
     * @return int
     */
    public static function count_records() {
        global $DB;

        $count = $DB->count_records(static::TABLE);
        return $count;
    }

    /**
     * Count a list of records.
     *
     * @param string $select
     * @param array $params
     * @return int
     */
    public static function count_records_select($select, $params = null) {
        global $DB;

        $count = $DB->count_records_select(static::TABLE, $select, $params);
        return $count;
    }

    /**
     * Check if a record exists by ID.
     *
     * @param int $id Record ID.
     * @return bool
     */
    public static function record_exists($id) {
        globaL $DB;
        return $DB->record_exists(static::TABLE, array('id' => $id));
    }

}