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
 * This file contains the csv exporter for a competency framework.
 *
 * @package   tool_lpimportcsv
 * @copyright 2015 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_lpimportcsv;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

use core_competency\api;
use stdClass;
use csv_export_writer;

/**
 * Export Competency framework.
 *
 * @package   tool_lp
 * @copyright 2015 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class framework_exporter {

    /** @var $framework \core_competency\competency_framework */
    private $framework = null;

    /** @var $error string */
    private $error = '';

    /**
     * Constructor - 
     */
    public function __construct($frameworkid) {
        $this->framework = api::read_framework($frameworkid);
    }

    /**
     * Export all the competencies from this framework to a csv file.
     */
    public function export() {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');

        $writer = new csv_export_writer();
        $filename = clean_param($this->framework->get_shortname() . '-' . $this->framework->get_idnumber(), PARAM_FILE);
        $writer->set_filename($filename);

        $headers = array(
            get_string('parentidnumber', 'tool_lpimportcsv'),
            get_string('idnumber', 'tool_lpimportcsv'),
            get_string('shortname', 'tool_lpimportcsv'),
            get_string('description', 'tool_lpimportcsv'),
            get_string('descriptionformat', 'tool_lpimportcsv'),
            get_string('scalevalues', 'tool_lpimportcsv'),
            get_string('scaleconfiguration', 'tool_lpimportcsv'),
            get_string('ruletype', 'tool_lpimportcsv'),
            get_string('ruleoutcome', 'tool_lpimportcsv'),
            get_string('ruleconfig', 'tool_lpimportcsv'),
            get_string('exportid', 'tool_lpimportcsv'),
            get_string('isframework', 'tool_lpimportcsv'),
            get_string('taxonomy', 'tool_lpimportcsv'),
        );

        $writer->add_data($headers);

        $row = array(
            '',
            $this->framework->get_idnumber(),
            $this->framework->get_shortname(),
            $this->framework->get_description(),
            $this->framework->get_descriptionformat(),
            $this->framework->get_scale()->compact_items(),
            $this->framework->get_scaleconfiguration(),
            '',
            '',
            '',
            '',
            true,
            implode(',', $this->framework->get_taxonomies())
        );
        $writer->add_data($row);

        $filters = array('competencyframeworkid' => $this->framework->get_id());
        $competencies = api::list_competencies($filters);
        // Index by id so we can lookup parents.
        $indexed = array();
        foreach ($competencies as $competency) {
            $indexed[$competency->get_id()] = $competency;
        }
        foreach ($competencies as $competency) {
            $parentidnumber = '';
            if ($competency->get_parentid() > 0) {
                $parent = $indexed[$competency->get_parentid()];
                $parentidnumber = $parent->get_idnumber();
            }

            $scalevalues = '';
            $scaleconfig = '';
            if ($competency->get_scaleid() > 0) {
                $scalevalues = $competency->get_scale()->compact_items();
                $scaleconfig = $competency->get_scaleconfiguration();
            }

            $ruleconfig = $competency->get_ruleconfig();
            if ($ruleconfig === null) {
                $ruleconfig = "null";
            }

            $row = array(
                $parentidnumber,
                $competency->get_idnumber(),
                $competency->get_shortname(),
                $competency->get_description(),
                $competency->get_descriptionformat(),
                $scalevalues,
                $scaleconfig,
                $competency->get_ruletype(),
                $competency->get_ruleoutcome(),
                $ruleconfig,
                $competency->get_id(),
                false,
                ''
            );

            $writer->add_data($row);
        }

        $writer->download_file();
    }
}
