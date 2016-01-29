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
 * This file contains the form add/update a competency framework.
 *
 * @package   tool_lpimportcsv
 * @copyright 2015 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lpimportcsv;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

use tool_lp\api;
use stdClass;
use csv_import_reader;

/**
 * Import Competency framework form.
 *
 * @package   tool_lp
 * @copyright 2015 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class framework_importer {

    /** @var string $error The errors message from reading the xml */
    var $error = '';

    /** @var array $tree The competencies tree */
    var $domains = array();

    public function fail($msg) {
        $this->error = $msg;
        return false;
    }

    /**
     * Constructor - parses the raw text for sanity.
     */
    public function __construct($text) {
        global $CFG;

        // The format of our records is Domain, Category, Category Description, Item, Item Description.
        // The idnumber is concatenated with the category names.
        require_once($CFG->libdir . '/csvlib.class.php');

        $type = 'competency_framework';
        $importid = csv_import_reader::get_new_iid($type);

        $importer = new csv_import_reader($importid, $type);

        if (!$importer->load_csv_content($text, 'utf-8', 'comma')) {
            $this->fail(get_string('invalidimportfile', 'tool_lpimportcsv'));
            $importer->cleanup();
            return;
        }

        if (!$importer->init()) {
            $this->fail(get_string('invalidimportfile', 'tool_lpimportcsv'));
            $importer->cleanup();
            return;
        }

        $domainid = 1;

        while ($row = $importer->next()) {
            $domain = $row[0];
            $category = $row[1];
            $categorydesc = $row[2];
            $item = $row[3];
            $itemdesc = $row[4];

            if (!isset($this->domains[$domain])) {
                $domainrecord = new stdClass();
                $domainrecord->name = $domain;
                $domainrecord->description = '';
                $domainrecord->idnumber = $domainid;
                $domainid += 1;
                $domainrecord->categories = array();

                $this->domains[$domain] = $domainrecord;
            }

            if (!isset($this->domains[$domain]->categories[$category])) {
                $categoryrecord = new stdClass();

                list($idnumber, $name) = explode(' ', $category, 2);
                $idnumber = trim($idnumber);
                $name = trim($name);

                $categoryrecord->name = $name;
                $categoryrecord->description = '';
                $categoryrecord->idnumber = $idnumber;
                $categoryrecord->items = array();

                $this->domains[$domain]->categories[$category] = $categoryrecord;
            }

            if (!isset($this->domains[$domain]->categories[$category]->items[$item])) {
                $itemrecord = new stdClass();

                list($idnumber, $name) = explode(' ', $item, 2);
                $idnumber = trim($idnumber);
                $name = trim($name);

                $itemrecord->name = $name;
                $itemrecord->description = $name;
                $itemrecord->idnumber = $idnumber;

                $this->domains[$domain]->categories[$category]->items[$item] = $itemrecord;
            }
        }

        $importer->close();
        $importer->cleanup();
    }

    /**
     * @return array of errors from parsing the xml.
     */
    public function get_error() {
        return $this->error;
    }

    public function create_competency($parent, $record, $framework, $addrule = false) {
        $competency = new stdClass();
        $competency->competencyframeworkid = $framework->get_id();
        $competency->shortname = trim(clean_param(shorten_text($record->name, 80), PARAM_TEXT));
        if (!empty($record->description)) {
            $competency->description = trim(clean_param($record->description, PARAM_TEXT));
        }
        if ($parent) {
            $competency->parentid = $parent->get_id();
        } else {
            $competency->parentid = 0;
        }
        $competency->idnumber = trim(clean_param($record->idnumber, PARAM_TEXT));

        if (!empty($competency->idnumber) && !empty($competency->shortname)) {
            $result = api::create_competency($competency);

            if ($addrule) {
                $result->set_ruletype('tool_lp\competency_rule_all');
                $result->set_ruleoutcome(\tool_lp\competency::OUTCOME_EVIDENCE);
                $result->update();
            }
            return $result;
        }
        return false;
    }


    /**
     * @param \tool_lp\competency_framework
     * @return boolean
     */
    public function import_to_framework($framework) {
        foreach ($this->domains as $domain) {
            $parentdomain = $this->create_competency(null, $domain, $framework);
            foreach ($domain->categories as $category) {
                $parentcategory = $this->create_competency($parentdomain, $category, $framework, true);
                foreach ($category->items as $item) {
                    $this->create_competency($parentcategory, $item, $framework);
                }
            }
        }
        return true;
    }
}
