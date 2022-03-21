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
 * This file keeps track of upgrades to the recentlyaccesseditems block
 *
 * Sometimes, changes between versions involve alterations to database structures
 * and other major things that may break installations.
 *
 * The upgrade function in this file will attempt to perform all the necessary
 * actions to upgrade your older installation to the current version.
 *
 * If there's something it cannot do itself, it will tell you what you need to do.
 *
 * The commands in here will all be database-neutral, using the methods of
 * database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package block_recentlyaccesseditems
 * @copyright 2019 Peter Dias
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the recentlyaccesseditems db table.
 *
 * @param $oldversion
 * @return bool
 */
function xmldb_block_recentlyaccesseditems_upgrade($oldversion, $block) {
    global $DB;

    // Automatically generated Moodle v3.7.0 release upgrade line.
    // Put any upgrade step following this.
    if ($oldversion < 2019052001) {
        // Query the items to be deleted as a list of IDs. We cannot delete directly from this as a
        // subquery because MySQL does not support delete with subqueries.
        $fordeletion = $DB->get_fieldset_sql("
                SELECT rai.id
                  FROM {block_recentlyaccesseditems} rai
             LEFT JOIN {course} c ON c.id = rai.courseid
             LEFT JOIN {course_modules} cm ON cm.id = rai.cmid
                 WHERE c.id IS NULL OR cm.id IS NULL");

        // Delete the array in chunks of 500 (Oracle does not support more than 1000 parameters,
        // let's leave some leeway, there are likely only one chunk anyway).
        $chunks = array_chunk($fordeletion, 500);
        foreach ($chunks as $chunk) {
            $DB->delete_records_list('block_recentlyaccesseditems', 'id', $chunk);
        }

        upgrade_block_savepoint(true, 2019052001, 'recentlyaccesseditems', false);
    }

    // Automatically generated Moodle v3.8.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.9.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2022030200) {
        $context = context_system::instance();

        // Begin looking for any and all customised /my pages.
        try {
            // Try to fetch all the custom /my pages along with any block matching block instances that are on them.
            // Casting subpagepattern to an int could fail if someone set it to a non-int, but core code doesn't do that.
            // Because of this, we do this within a try with a dml_exception catch that implements a slower fallback that
            // doesn't use the cast-join pattern.
            $select = "SELECT mp.id,
                              bi.id AS instanceid,
                              bi.defaultregion ";
            $sql = "FROM {my_pages} mp
               LEFT JOIN {block_instances} bi ON " . $DB->sql_cast_char2int('bi.subpagepattern') . " = mp.id
                                             AND bi.blockname = :block
                                             AND bi.pagetypepattern = :type
                   WHERE mp.name = :name
                     AND mp.private = :private";
            $params = [
                'block' => 'recentlyaccesseditems',
                'type' => 'my-index',
                'name' => '__default',
                'private' => 1
            ];
            $total = $DB->count_records_sql("SELECT COUNT(*) $sql", $params);
            if ($total > 0) {
                $items = $DB->get_recordset_sql($select.$sql, $params);
            }
            $fallback = false;
        } catch (\dml_exception $e) {
            // There is a chance the database will object to the cast-join above, so we implement a fallback method.
            $params = [
                'name' => '__default',
                'private' => 1
            ];
            $total = $DB->count_records('my_pages', $params);
            if ($total > 0) {
                $items = $DB->get_recordset('my_pages', $params);
            }
            $fallback = true;
        }

        if ($total > 0) {
            $pbar = new progress_bar('recentlyaccesseditemsblocks', 500, true);
            $i = 0;
            $pbar->update($i, $total, "Processing page - $i/$total.");

            foreach ($items as $item) {
                if ($fallback) {
                    // If we are in fallback item is a page record, we need to check for instances directly.
                    $params = [
                        'blockname' => 'recentlyaccesseditems',
                        'pagetypepattern' => 'my-index',
                        'subpagepattern' => $item->id
                    ];
                    $blockinstance = $DB->get_record('block_instances', $params);

                    $instanceid = $blockinstance->id ?? false;
                    $defaultregion = $blockinstance->defaultregion ?? false;
                } else {
                    // In this case, item contains instance info and the page id.
                    $instanceid = $item->instanceid;
                    $defaultregion = $item->defaultregion;
                }

                if (!$instanceid) {
                    // Insert the recentlyaccesseditems block into the page.
                    $blockinstance = new stdClass;
                    $blockinstance->blockname = 'recentlyaccesseditems';
                    $blockinstance->parentcontextid = $context->id;
                    $blockinstance->showinsubcontexts = false;
                    $blockinstance->pagetypepattern = 'my-index';
                    $blockinstance->subpagepattern = $item->id;
                    $blockinstance->defaultregion = 'side-post';
                    $blockinstance->defaultweight = -10;
                    $blockinstance->timecreated = time();
                    $blockinstance->timemodified = time();
                    $DB->insert_record('block_instances', $blockinstance);
                } else if ($defaultregion !== 'side-post') {
                    $DB->set_field('block_instances', 'defaultregion', 'side-post', ['id' => $instanceid]);
                }

                if (($i % 100) == 0) {
                    // Decrease how often we call the progress bar update. Speeds up the process.
                    $pbar->update($i, $total, "Processing page - $i/$total.");
                }
                $i++;
            }
            $pbar->update($total, $total, "Processing page - $total/$total.");
            $items->close();
        }

        upgrade_block_savepoint(true, 2022030200, 'recentlyaccesseditems', false);
    }

    return true;
}
