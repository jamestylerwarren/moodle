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

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use tool_lp\external;
use tool_lp\plan;

/**
 * External learning plans webservice API tests.
 *
 * @package tool_lp
 * @copyright 2015 Damyon Wiese
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_lp_external_testcase extends externallib_advanced_testcase {

    /** @var stdClass $learningplancreator User with enough permissions to create */
    protected $creator = null;

    /** @var stdClass $learningplanuser User with enough permissions to view */
    protected $user = null;

    /** @var int Creator role id */
    protected $creatorrole = null;

    /** @var int User role id */
    protected $userrole = null;

    /**
     * Setup function - we will create a course and add an assign instance to it.
     */
    protected function setUp() {
        global $DB;

        $this->resetAfterTest(true);

        // Create some users.
        $creator = $this->getDataGenerator()->create_user();
        $user = $this->getDataGenerator()->create_user();
        $syscontext = context_system::instance();

        $this->creatorrole = create_role('Creator role', 'creatorrole', 'learning plan creator role description');
        $this->userrole = create_role('User role', 'userrole', 'learning plan user role description');

        assign_capability('tool/lp:competencymanage', CAP_ALLOW, $this->creatorrole, $syscontext->id);
        assign_capability('tool/lp:competencyview', CAP_ALLOW, $this->userrole, $syscontext->id);
        assign_capability('tool/lp:planmanage', CAP_ALLOW, $this->creatorrole, $syscontext->id);
        assign_capability('tool/lp:planmanageown', CAP_ALLOW, $this->creatorrole, $syscontext->id);
        assign_capability('tool/lp:planviewall', CAP_ALLOW, $this->creatorrole, $syscontext->id);
        assign_capability('tool/lp:templatemanage', CAP_ALLOW, $this->creatorrole, $syscontext->id);
        assign_capability('tool/lp:templatecompetencymanage', CAP_ALLOW, $this->creatorrole, $syscontext->id);

        role_assign($this->creatorrole, $creator->id, $syscontext->id);
        role_assign($this->userrole, $user->id, $syscontext->id);

        $this->creator = $creator;
        $this->user = $user;
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * Test we can't create a competency framework with only read permissions.
     */
    public function test_create_competency_frameworks_with_read_permissions() {
        $this->setExpectedException('required_capability_exception');
        $this->setUser($this->user);
        $result = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
    }

    /**
     * Test we can create a competency framework with manage permissions.
     */
    public function test_create_competency_frameworks_with_manage_permissions() {
        $this->setUser($this->creator);
        $result = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $result = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $result);

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname', $result->shortname);
        $this->assertEquals('idnumber', $result->idnumber);
        $this->assertEquals('description', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(true, $result->visible);
    }

    /**
     * Test we cannot create a competency framework with nasty data.
     */
    public function test_create_competency_frameworks_with_nasty_data() {
        $this->setUser($this->creator);
        $this->setExpectedException('invalid_parameter_exception');
        $result = external::create_competency_framework('short<a href="">', 'id;"number', 'de<>\\..scription', FORMAT_HTML, true);
    }

    /**
     * Test we can read a competency framework with manage permissions.
     */
    public function test_read_competency_frameworks_with_manage_permissions() {
        $this->setUser($this->creator);
        $result = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $result = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $result);

        $id = $result->id;
        $result = external::read_competency_framework($id);
        $result = (object) external_api::clean_returnvalue(external::read_competency_framework_returns(), $result);

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname', $result->shortname);
        $this->assertEquals('idnumber', $result->idnumber);
        $this->assertEquals('description', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(true, $result->visible);
    }

    /**
     * Test we can read a competency framework with read permissions.
     */
    public function test_read_competency_frameworks_with_read_permissions() {
        $this->setUser($this->creator);
        $result = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $result = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $result);

        // Switch users to someone with less permissions.
        $this->setUser($this->user);
        $id = $result->id;
        $result = external::read_competency_framework($id);
        $result = (object) external_api::clean_returnvalue(external::read_competency_framework_returns(), $result);

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname', $result->shortname);
        $this->assertEquals('idnumber', $result->idnumber);
        $this->assertEquals('description', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(true, $result->visible);
    }

    /**
     * Test we can delete a competency framework with manage permissions.
     */
    public function test_delete_competency_frameworks_with_manage_permissions() {
        $this->setUser($this->creator);
        $result = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $result = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $result);

        $id = $result->id;
        $result = external::delete_competency_framework($id);
        $result = external_api::clean_returnvalue(external::delete_competency_framework_returns(), $result);

        $this->assertTrue($result);
    }

    /**
     * Test we can delete a competency framework with read permissions.
     */
    public function test_delete_competency_frameworks_with_read_permissions() {
        $this->setExpectedException('required_capability_exception');
        $this->setUser($this->creator);
        $result = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $result = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $result);

        $id = $result->id;
        // Switch users to someone with less permissions.
        $this->setUser($this->user);
        $result = external::delete_competency_framework($id);
    }

    /**
     * Test we can update a competency framework with manage permissions.
     */
    public function test_update_competency_frameworks_with_manage_permissions() {
        $this->setUser($this->creator);
        $result = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $result = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $result);

        $result = external::update_competency_framework($result->id, 'shortname2', 'idnumber2', 'description2', FORMAT_PLAIN, false);
        $result = external_api::clean_returnvalue(external::update_competency_framework_returns(), $result);

        $this->assertTrue($result);
    }

    /**
     * Test we can update a competency framework with read permissions.
     */
    public function test_update_competency_frameworks_with_read_permissions() {
        $this->setExpectedException('required_capability_exception');
        $this->setUser($this->creator);
        $result = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $result = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $result);

        $this->setUser($this->user);
        $result = external::update_competency_framework($result->id, 'shortname2', 'idnumber2', 'description2', FORMAT_PLAIN, false);
    }

    /**
     * Test we can list and count competency frameworks with manage permissions.
     */
    public function test_list_and_count_competency_frameworks_with_manage_permissions() {
        $this->setUser($this->creator);
        $result = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $result = external::create_competency_framework('shortname2', 'idnumber2', 'description', FORMAT_HTML, true);
        $result = external::create_competency_framework('shortname3', 'idnumber3', 'description', FORMAT_HTML, true);

        $result = external::count_competency_frameworks(array());
        $result = external_api::clean_returnvalue(external::count_competency_frameworks_returns(), $result);

        $this->assertEquals($result, 3);

        $result = external::list_competency_frameworks(array(), 'shortname', 'ASC', 0, 10);
        $result = external_api::clean_returnvalue(external::list_competency_frameworks_returns(), $result);

        $this->assertEquals(count($result), 3);
        $result = (object) $result[0];

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname', $result->shortname);
        $this->assertEquals('idnumber', $result->idnumber);
        $this->assertEquals('description', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(true, $result->visible);
    }

    /**
     * Test we can list and count competency frameworks with read permissions.
     */
    public function test_list_and_count_competency_frameworks_with_read_permissions() {
        $this->setUser($this->creator);
        $result = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $result = external::create_competency_framework('shortname2', 'idnumber2', 'description', FORMAT_HTML, true);
        $result = external::create_competency_framework('shortname3', 'idnumber3', 'description', FORMAT_HTML, true);

        $this->setUser($this->user);
        $result = external::count_competency_frameworks(array());
        $result = external_api::clean_returnvalue(external::count_competency_frameworks_returns(), $result);

        $this->assertEquals($result, 3);

        $result = external::list_competency_frameworks(array(), 'shortname', 'ASC', 0, 10);
        $result = external_api::clean_returnvalue(external::list_competency_frameworks_returns(), $result);

        $this->assertEquals(count($result), 3);
        $result = (object) $result[0];

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname', $result->shortname);
        $this->assertEquals('idnumber', $result->idnumber);
        $this->assertEquals('description', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(true, $result->visible);
    }

    /**
     * Test we can re-order competency frameworks.
     */
    public function test_reorder_competency_framework() {
        $this->setUser($this->creator);
        $f1 = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $f2 = external::create_competency_framework('shortname2', 'idnumber2', 'description', FORMAT_HTML, true);
        $f3 = external::create_competency_framework('shortname3', 'idnumber3', 'description', FORMAT_HTML, true);
        $f4 = external::create_competency_framework('shortname4', 'idnumber4', 'description', FORMAT_HTML, true);
        $f5 = external::create_competency_framework('shortname5', 'idnumber5', 'description', FORMAT_HTML, true);
        $f6 = external::create_competency_framework('shortname6', 'idnumber6', 'description', FORMAT_HTML, true);

        // This is a move up.
        $result = external::reorder_competency_framework($f5->id, $f2->id);
        $result = external::list_competency_frameworks(array(), 'sortorder', 'ASC', 0, 10);
        $result = external_api::clean_returnvalue(external::list_competency_frameworks_returns(), $result);

        $r1 = (object) $result[0];
        $r2 = (object) $result[1];
        $r3 = (object) $result[2];
        $r4 = (object) $result[3];
        $r5 = (object) $result[4];
        $r6 = (object) $result[5];

        $this->assertEquals($f1->id, $r1->id);
        $this->assertEquals($f5->id, $r2->id);
        $this->assertEquals($f2->id, $r3->id);
        $this->assertEquals($f3->id, $r4->id);
        $this->assertEquals($f4->id, $r5->id);
        $this->assertEquals($f6->id, $r6->id);

        // This is a move down.
        $result = external::reorder_competency_framework($f5->id, $f4->id);
        $result = external::list_competency_frameworks(array(), 'sortorder', 'ASC', 0, 10);
        $result = external_api::clean_returnvalue(external::list_competency_frameworks_returns(), $result);

        $r1 = (object) $result[0];
        $r2 = (object) $result[1];
        $r3 = (object) $result[2];
        $r4 = (object) $result[3];
        $r5 = (object) $result[4];
        $r6 = (object) $result[5];

        $this->assertEquals($f1->id, $r1->id);
        $this->assertEquals($f2->id, $r2->id);
        $this->assertEquals($f3->id, $r3->id);
        $this->assertEquals($f4->id, $r4->id);
        $this->assertEquals($f5->id, $r5->id);
        $this->assertEquals($f6->id, $r6->id);

        $this->setExpectedException('required_capability_exception');
        $this->setUser($this->user);
        $result = external::reorder_competency_framework($f5->id, $f4->id);
    }

    /**
     * Test we can't create a competency with only read permissions.
     */
    public function test_create_competency_with_read_permissions() {
        $this->setExpectedException('required_capability_exception');
        $framework = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $framework = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $framework);
        $this->setUser($this->user);
        $competency = external::create_competency('shortname', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
    }

    /**
     * Test we can create a competency with manage permissions.
     */
    public function test_create_competency_with_manage_permissions() {
        $this->setUser($this->creator);
        $framework = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $framework = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $framework);

        $competency = external::create_competency('shortname', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
        $competency = (object) external_api::clean_returnvalue(external::create_competency_returns(), $competency);

        $this->assertGreaterThan(0, $competency->timecreated);
        $this->assertGreaterThan(0, $competency->timemodified);
        $this->assertEquals($this->creator->id, $competency->usermodified);
        $this->assertEquals('shortname', $competency->shortname);
        $this->assertEquals('idnumber', $competency->idnumber);
        $this->assertEquals('description', $competency->description);
        $this->assertEquals(FORMAT_HTML, $competency->descriptionformat);
        $this->assertEquals(true, $competency->visible);
        $this->assertEquals(0, $competency->parentid);
        $this->assertEquals($framework->id, $competency->competencyframeworkid);
    }

    /**
     * Test we cannot create a competency with nasty data.
     */
    public function test_create_competency_with_nasty_data() {
        $this->setUser($this->creator);
        $framework = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $framework = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $framework);
        $this->setExpectedException('invalid_parameter_exception');
        $competency = external::create_competency('shortname<a href="">', 'id;"number', 'de<>\\..scription', FORMAT_HTML, true, $framework->id, 0);
    }

    /**
     * Test we can read a competency with manage permissions.
     */
    public function test_read_competencies_with_manage_permissions() {
        $this->setUser($this->creator);
        $framework = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $framework = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $framework);
        $result = external::create_competency('shortname', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
        $result = (object) external_api::clean_returnvalue(external::create_competency_returns(), $result);

        $id = $result->id;
        $result = external::read_competency($id);
        $result = (object) external_api::clean_returnvalue(external::read_competency_returns(), $result);

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname', $result->shortname);
        $this->assertEquals('idnumber', $result->idnumber);
        $this->assertEquals('description', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(true, $result->visible);
        $this->assertEquals(0, $result->parentid);
        $this->assertEquals(0, $result->parentid);
    }

    /**
     * Test we can read a competency with read permissions.
     */
    public function test_read_competencies_with_read_permissions() {
        $this->setUser($this->creator);
        $framework = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $framework = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $framework);
        $result = external::create_competency('shortname', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
        $result = (object) external_api::clean_returnvalue(external::create_competency_returns(), $result);

        // Switch users to someone with less permissions.
        $this->setUser($this->user);
        $id = $result->id;
        $result = external::read_competency($id);
        $result = (object) external_api::clean_returnvalue(external::read_competency_returns(), $result);

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname', $result->shortname);
        $this->assertEquals('idnumber', $result->idnumber);
        $this->assertEquals('description', $result->description);
        $this->assertEquals(FORMAT_HTML, $result->descriptionformat);
        $this->assertEquals(true, $result->visible);
        $this->assertEquals(0, $result->parentid);
        $this->assertEquals(0, $result->parentid);
    }

    /**
     * Test we can delete a competency with manage permissions.
     */
    public function test_delete_competency_with_manage_permissions() {
        $this->setUser($this->creator);
        $framework = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $framework = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $framework);
        $result = external::create_competency('shortname', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
        $result = (object) external_api::clean_returnvalue(external::create_competency_returns(), $result);

        $id = $result->id;
        $result = external::delete_competency($id);
        $result = external_api::clean_returnvalue(external::delete_competency_returns(), $result);

        $this->assertTrue($result);
    }

    /**
     * Test we can delete a competency with read permissions.
     */
    public function test_delete_competency_with_read_permissions() {
        $this->setExpectedException('required_capability_exception');
        $this->setUser($this->creator);
        $framework = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $framework = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $framework);
        $result = external::create_competency('shortname', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
        $result = (object) external_api::clean_returnvalue(external::create_competency_returns(), $result);

        $id = $result->id;
        // Switch users to someone with less permissions.
        $this->setUser($this->user);
        $result = external::delete_competency($id);
    }

    /**
     * Test we can update a competency with manage permissions.
     */
    public function test_update_competency_with_manage_permissions() {
        $this->setUser($this->creator);
        $framework = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $framework = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $framework);
        $result = external::create_competency('shortname', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
        $result = (object) external_api::clean_returnvalue(external::create_competency_returns(), $result);

        $result = external::update_competency($result->id, 'shortname2', 'idnumber2', 'description2', FORMAT_HTML, false);
        $result = external_api::clean_returnvalue(external::update_competency_returns(), $result);

        $this->assertTrue($result);
    }

    /**
     * Test we can update a competency with read permissions.
     */
    public function test_update_competency_with_read_permissions() {
        $this->setExpectedException('required_capability_exception');
        $this->setUser($this->creator);
        $framework = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $framework = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $framework);
        $result = external::create_competency('shortname', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
        $result = (object) external_api::clean_returnvalue(external::create_competency_returns(), $result);

        $this->setUser($this->user);
        $result = external::update_competency($result->id, 'shortname2', 'idnumber2', 'description2', FORMAT_HTML, false);
    }

    /**
     * Test we can list and count competencies with manage permissions.
     */
    public function test_list_and_count_competencies_with_manage_permissions() {
        $this->setUser($this->creator);
        $framework = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $framework = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $framework);
        $result = external::create_competency('shortname', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
        $result = external::create_competency('shortname2', 'idnumber2', 'description2', FORMAT_HTML, true, $framework->id, 0);
        $result = external::create_competency('shortname3', 'idnumber3', 'description3', FORMAT_HTML, true, $framework->id, 0);

        $result = external::count_competencies(array());
        $result = external_api::clean_returnvalue(external::count_competencies_returns(), $result);

        $this->assertEquals($result, 3);

        $result = external::list_competencies(array(), 'shortname', 'ASC', 0, 10);
        $result = external_api::clean_returnvalue(external::list_competencies_returns(), $result);

        $this->assertEquals(count($result), 3);
        $result = (object) $result[0];

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname', $result->shortname);
        $this->assertEquals('idnumber', $result->idnumber);
        $this->assertEquals('description', $result->description);
        $this->assertEquals(true, $result->visible);
    }

    /**
     * Test we can list and count competencies with read permissions.
     */
    public function test_list_and_count_competencies_with_read_permissions() {
        $this->setUser($this->creator);
        $framework = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $framework = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $framework);
        $result = external::create_competency('shortname', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
        $result = external::create_competency('shortname2', 'idnumber2', 'description2', FORMAT_HTML, true, $framework->id, 0);
        $result = external::create_competency('shortname3', 'idnumber3', 'description3', FORMAT_HTML, true, $framework->id, 0);

        $this->setUser($this->user);

        $result = external::count_competencies(array());
        $result = external_api::clean_returnvalue(external::count_competencies_returns(), $result);

        $this->assertEquals($result, 3);

        $result = external::list_competencies(array(), 'shortname', 'ASC', 0, 10);
        $result = external_api::clean_returnvalue(external::list_competencies_returns(), $result);

        $this->assertEquals(count($result), 3);
        $result = (object) $result[0];

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname', $result->shortname);
        $this->assertEquals('idnumber', $result->idnumber);
        $this->assertEquals('description', $result->description);
        $this->assertEquals(true, $result->visible);
    }

    /**
     * Test we can search for competencies.
     */
    public function test_search_competencies_with_read_permissions() {
        $this->setUser($this->creator);
        $framework = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $framework = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $framework);
        $result = external::create_competency('shortname', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
        $result = external::create_competency('shortname2', 'idnumber2', 'description2', FORMAT_HTML, true, $framework->id, 0);
        $result = external::create_competency('shortname3', 'idnumber3', 'description3', FORMAT_HTML, true, $framework->id, 0);

        $this->setUser($this->user);

        $result = external::search_competencies('short', $framework->id);
        $result = external_api::clean_returnvalue(external::search_competencies_returns(), $result);

        $this->assertEquals(count($result), 3);
        $result = (object) $result[0];

        $this->assertGreaterThan(0, $result->timecreated);
        $this->assertGreaterThan(0, $result->timemodified);
        $this->assertEquals($this->creator->id, $result->usermodified);
        $this->assertEquals('shortname', $result->shortname);
        $this->assertEquals('idnumber', $result->idnumber);
        $this->assertEquals('description', $result->description);
        $this->assertEquals(true, $result->visible);
    }

    /**
     * Test plans creation and updates.
     */
    public function test_create_and_update_plans() {
        $syscontext = context_system::instance();

        $this->setUser($this->creator);
        $plan0 = external::create_plan('Complete plan', 'A description', FORMAT_HTML, $this->creator->id, 0, plan::STATUS_COMPLETE, 0);

        $this->setUser($this->user);

        try {
            $plan1 = external::create_plan('Draft plan (they can not with the default capabilities)', 'A description', FORMAT_HTML, $this->user->id, 0, plan::STATUS_DRAFT, 0);
            $this->fail('Exception expected due to not permissions to create draft plans');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        assign_capability('tool/lp:plancreatedraft', CAP_ALLOW, $this->userrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($this->user);

        $plan2 = external::create_plan('Draft plan', 'A description', FORMAT_HTML, $this->user->id, 0, plan::STATUS_DRAFT, 0);

        try {
            $plan3 = external::create_plan('Active plan (they can not)', 'A description', FORMAT_HTML, $this->user->id, 0, plan::STATUS_ACTIVE, 0);
            $this->fail('Exception expected due to not permissions to create active plans');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
        try {
            $plan3 = external::update_plan($plan2['id'], 'Updated active plan', 'A description', FORMAT_HTML, $this->user->id, 0, plan::STATUS_COMPLETE, 0);
            $this->fail('Exception expected due to not permissions to update plans to complete status');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        assign_capability('tool/lp:planmanageown', CAP_ALLOW, $this->userrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $plan3 = external::create_plan('Active plan', 'A description', FORMAT_HTML, $this->user->id, 0, plan::STATUS_ACTIVE, 0);
        $plan4 = external::create_plan('Complete plan', 'A description', FORMAT_HTML, $this->user->id, 0, plan::STATUS_COMPLETE, 0);
        try {
            $plan4 = external::create_plan('Plan for another user', 'A description', FORMAT_HTML, $this->creator->id, 0, plan::STATUS_COMPLETE, 0);
            $this->fail('Exception expected due to not permissions to manage other users plans');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        try {
            $plan0 = external::update_plan($plan0['id'], 'Can not update other users plans', 'A description', FORMAT_HTML, $this->user->id, 0, plan::STATUS_ACTIVE, 0);
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        unassign_capability('tool/lp:planmanageown', $this->userrole, $syscontext->id);
        unassign_capability('tool/lp:plancreatedraft', $this->userrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        try {
            $plan1 = external::update_plan($plan2['id'], 'Can not be updated even if they created it', 'A description', FORMAT_HTML, $this->user->id, 0, plan::STATUS_COMPLETE, 0);
            $this->fail('Exception expected due to not permissions to create draft plan');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
    }

    /**
     * Test that we can read plans.
     */
    public function test_read_plans() {
        $this->setUser($this->creator);

        $syscontext = context_system::instance();

        $plan1 = external::create_plan('Plan draft by creator', 'A description', FORMAT_HTML, $this->user->id, 0, plan::STATUS_DRAFT, 0);
        $plan2 = external::create_plan('Plan active by creator', 'A description', FORMAT_HTML, $this->user->id, 0, plan::STATUS_ACTIVE, 0);
        $plan3 = external::create_plan('Plan complete by creator', 'A description', FORMAT_HTML, $this->user->id, 0, plan::STATUS_COMPLETE, 0);

        $this->assertEquals((Array)$plan1, external::read_plan($plan1['id']));
        $this->assertEquals((Array)$plan2, external::read_plan($plan2['id']));
        $this->assertEquals((Array)$plan3, external::read_plan($plan3['id']));

        $this->setUser($this->user);

        // The normal user can not edit these plans.
        $plan1['usercanupdate'] = false;
        $plan2['usercanupdate'] = false;
        $plan3['usercanupdate'] = false;

        // You need planmanage, planmanageown or plancreatedraft to see draft plans.
        try {
            external::read_plan($plan1['id']);
            $this->fail('Exception expected due to not permissions to read draft plan');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
        $this->assertEquals((Array)$plan2, external::read_plan($plan2['id']));
        $this->assertEquals((Array)$plan3, external::read_plan($plan3['id']));

        assign_capability('tool/lp:plancreatedraft', CAP_ALLOW, $this->userrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertEquals((Array)$plan1, external::read_plan($plan1['id']));

        assign_capability('tool/lp:planviewown', CAP_PROHIBIT, $this->userrole, $syscontext->id);
        unassign_capability('tool/lp:plancreatedraft', $this->userrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        try {
            $plan = external::read_plan($plan2['id']);
            $this->fail('Exception expected due to not permissions to view own plans');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
    }

    public function test_delete_plans() {
        $this->setUser($this->creator);

        $syscontext = context_system::instance();

        $plan1 = external::create_plan('1', 'A description', FORMAT_HTML, $this->user->id, 0, plan::STATUS_COMPLETE, 0);
        $plan2 = external::create_plan('2', 'A description', FORMAT_HTML, $this->user->id, 0, plan::STATUS_COMPLETE, 0);
        $plan3 = external::create_plan('3', 'A description', FORMAT_HTML, $this->creator->id, 0, plan::STATUS_COMPLETE, 0);

        $this->assertTrue(external::delete_plan($plan1['id']));

        unassign_capability('tool/lp:planmanage', $this->creatorrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        try {
            external::delete_plan($plan2['id']);
            $this->fail('Exception expected due to not permissions to manage plans');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        $this->setUser($this->user);

        // Can not delete plans created by other users.
        try {
            external::delete_plan($plan2['id']);
            $this->fail('Exception expected due to not permissions to manage plans');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        assign_capability('tool/lp:planmanageown', CAP_ALLOW, $this->userrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertTrue(external::delete_plan($plan2['id']));

        // Can not delete plans created for other users.
        try {
            external::delete_plan($plan3['id']);
            $this->fail('Exception expected due to not permissions to manage plans');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        $plan4 = external::create_plan('4', 'A description', FORMAT_HTML, $this->user->id, 0, plan::STATUS_COMPLETE, 0);
        $this->assertTrue(external::delete_plan($plan4['id']));
    }

    public function test_add_competency_to_template() {
        $this->setUser($this->creator);

        $syscontext = context_system::instance();

        // Create a template.
        $template = external::create_template('shortname', 'idnumber', time(), 'description', FORMAT_HTML, true);
        $template = (object) external_api::clean_returnvalue(external::create_template_returns(), $template);

        // Create a competency.
        $framework = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $framework = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $framework);
        $competency = external::create_competency('shortname', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
        $competency = (object) external_api::clean_returnvalue(external::create_competency_returns(), $competency);

        // Add the competency.
        external::add_competency_to_template($template->id, $competency->id);

        // Check that it was added.
        $this->assertEquals(1, external::count_competencies_in_template($template->id));

        // Unassign capability.
        unassign_capability('tool/lp:templatecompetencymanage', $this->creatorrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        // Check we can not add the competency now.
        try {
            external::add_competency_to_template($template->id, $competency->id);
            $this->fail('Exception expected due to not permissions to manage template competencies');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
    }

    public function test_remove_competency_from_template() {
        $this->setUser($this->creator);

        $syscontext = context_system::instance();

        // Create a template.
        $template = external::create_template('shortname', 'idnumber', time(), 'description', FORMAT_HTML, true);
        $template = (object) external_api::clean_returnvalue(external::create_template_returns(), $template);

        // Create a competency.
        $framework = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $framework = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $framework);
        $competency = external::create_competency('shortname', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
        $competency = (object) external_api::clean_returnvalue(external::create_competency_returns(), $competency);

        // Add the competency.
        external::add_competency_to_template($template->id, $competency->id);

        // Check that it was added.
        $this->assertEquals(1, external::count_competencies_in_template($template->id));

        // Check that we can remove the competency.
        external::remove_competency_from_template($template->id, $competency->id);

        // Check that it was removed.
        $this->assertEquals(0, external::count_competencies_in_template($template->id));

        // Unassign capability.
        unassign_capability('tool/lp:templatecompetencymanage', $this->creatorrole, $syscontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        // Check we can not remove the competency now.
        try {
            external::add_competency_to_template($template->id, $competency->id);
            $this->fail('Exception expected due to not permissions to manage template competencies');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
    }

    /**
     * Test we can re-order competency frameworks.
     */
    public function test_reorder_template_competencies() {
        $this->setUser($this->creator);

        $syscontext = context_system::instance();

        // Create a template.
        $template = external::create_template('shortname', 'idnumber', time(), 'description', FORMAT_HTML, true);
        $template = (object) external_api::clean_returnvalue(external::create_template_returns(), $template);

        // Create a competency framework.
        $framework = external::create_competency_framework('shortname', 'idnumber', 'description', FORMAT_HTML, true);
        $framework = (object) external_api::clean_returnvalue(external::create_competency_framework_returns(), $framework);

        // Create multiple competencies.
        $competency1 = external::create_competency('shortname1', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
        $competency1 = (object) external_api::clean_returnvalue(external::create_competency_returns(), $competency1);
        $competency2 = external::create_competency('shortname2', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
        $competency2 = (object) external_api::clean_returnvalue(external::create_competency_returns(), $competency2);
        $competency3 = external::create_competency('shortname3', 'idnumber', 'description', FORMAT_HTML, true, $framework->id, 0);
        $competency3 = (object) external_api::clean_returnvalue(external::create_competency_returns(), $competency3);

        // Add the competencies.
        external::add_competency_to_template($template->id, $competency1->id);
        external::add_competency_to_template($template->id, $competency2->id);
        external::add_competency_to_template($template->id, $competency3->id);

        // This is a move up.
        external::reorder_template_competency($template->id, $competency3->id, $competency2->id);
        $result = external::list_competencies_in_template($template->id);
        $result = external_api::clean_returnvalue(external::list_competencies_in_template_returns(), $result);

        $r1 = (object) $result[0];
        $r2 = (object) $result[1];
        $r3 = (object) $result[2];

        $this->assertEquals($competency1->id, $r1->id);
        $this->assertEquals($competency3->id, $r2->id);
        $this->assertEquals($competency2->id, $r3->id);

        // This is a move down.
        external::reorder_template_competency($template->id, $competency1->id, $competency3->id);
        $result = external::list_competencies_in_template($template->id);
        $result = external_api::clean_returnvalue(external::list_competencies_in_template_returns(), $result);

        $r1 = (object) $result[0];
        $r2 = (object) $result[1];
        $r3 = (object) $result[2];

        $this->assertEquals($competency3->id, $r1->id);
        $this->assertEquals($competency1->id, $r2->id);
        $this->assertEquals($competency2->id, $r3->id);

        $this->setExpectedException('required_capability_exception');
        $this->setUser($this->user);
        external::reorder_template_competency($template->id, $competency1->id, $competency2->id);
    }

    /**
     * Test that we can return scale values for a scale with the scale ID.
     */
    public function test_get_scale_values() {
        global $DB;
        // Create a scale.
        $record = new stdClass();
        $record->courseid = 0;
        $record->userid = $this->creator->id;
        $record->name = 'Test scale';
        $record->scale = 'Poor, Not good, Okay, Fine, Excellent';
        $record->description = '<p>Test scale description.</p>';
        $record->descriptionformat = 1;
        $record->timemodified = time();
        $scaleid = $DB->insert_record('scale', $record);
        // Expected return value.
        $expected = array(array(
                'id' => 1,
                'name' => 'Excellent'
            ), array(
                'id' => 2,
                'name' => 'Fine'
            ), array(
                'id' => 3,
                'name' => 'Okay'
            ), array(
                'id' => 4,
                'name' => 'Not good'
            ), array(
                'id' => 5,
                'name' => 'Poor'
            )
        );
        // Call the webservice.
        $result = external::get_scale_values($scaleid);
        $this->assertEquals($expected, $result);
    }
}