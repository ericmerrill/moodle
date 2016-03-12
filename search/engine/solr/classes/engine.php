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
 * Solr engine.
 *
 * @package    search_solr
 * @copyright  2015 Daniel Neis Araujo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace search_solr;

defined('MOODLE_INTERNAL') || die();

/**
 * Solr engine.
 *
 * @package    search_solr
 * @copyright  2015 Daniel Neis Araujo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engine extends \core_search\engine {

    /**
     * @var string The date format used by solr.
     */
    const DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * @var int Commit documents interval (number of miliseconds).
     */
    const AUTOCOMMIT_WITHIN = 15000;

    /**
     * @var int Highlighting fragsize.
     */
    const FRAG_SIZE = 500;

    /**
     * @var \SolrClient
     */
    protected $client = null;

    /**
     * @var \curl
     */
    protected $curl = null;

    /**
     * @var array Fields that can be highlighted.
     */
    protected $highlightfields = array('content', 'description1', 'description2');

    /**
     * Prepares a Solr query, applies filters and executes it returning its results.
     *
     * @throws \core_search\engine_exception
     * @param  stdClass     $filters Containing query and filters.
     * @param  array        $usercontexts Contexts where the user has access. True if the user can access all contexts.
     * @return \core_search\document[] Results or false if no results
     */
    public function execute_query($filters, $usercontexts) {

        // Let's keep these changes internal.
        $data = clone $filters;

        // If there is any problem we trigger the exception as soon as possible.
        $this->client = $this->get_search_client();

        $serverstatus = $this->is_server_ready();
        if ($serverstatus !== true) {
            throw new \core_search\engine_exception('engineserverstatus', 'search');
        }

        $query = new \SolrQuery();
        $this->set_query($query, $data->q);
        $this->add_fields($query);

        // Search filters applied, we don't cache these filters as we don't want to pollute the cache with tmp filters
        // we are really interested in caching contexts filters instead.
        if (!empty($data->title)) {
            $query->addFilterQuery('{!field cache=false f=title}' . $data->title);
        }
        if (!empty($data->areaid)) {
            // Even if it is only supposed to contain PARAM_ALPHANUMEXT, better to prevent.
            $query->addFilterQuery('{!field cache=false f=areaid}' . $data->areaid);
        }

        if (!empty($data->timestart) or !empty($data->timeend)) {
            if (empty($data->timestart)) {
                $data->timestart = '*';
            } else {
                $data->timestart = \search_solr\document::format_time_for_engine($data->timestart);
            }
            if (empty($data->timeend)) {
                $data->timeend = '*';
            } else {
                $data->timeend = \search_solr\document::format_time_for_engine($data->timeend);
            }

            // No cache.
            $query->addFilterQuery('{!cache=false}modified:[' . $data->timestart . ' TO ' . $data->timeend . ']');
        }

        // And finally restrict it to the context where the user can access, we want this one cached.
        // If the user can access all contexts $usercontexts value is just true, we don't need to filter
        // in that case.
        if ($usercontexts && is_array($usercontexts)) {
            if (!empty($data->areaid)) {
                $query->addFilterQuery('contextid:(' . implode(' OR ', $usercontexts[$data->areaid]) . ')');
            } else {
                // Join all area contexts into a single array and implode.
                $allcontexts = array();
                foreach ($usercontexts as $areacontexts) {
                    foreach ($areacontexts as $contextid) {
                        // Ensure they are unique.
                        $allcontexts[$contextid] = $contextid;
                    }
                }
                $query->addFilterQuery('contextid:(' . implode(' OR ', $allcontexts) . ')');
            }
        }

        try {
            if ($this->file_indexing_enabled()) {
                // Now group records by solr_filegroupingid. Limit to 3 results per group.
                $query->setGroup(true);
                $query->setGroupLimit(3);
                $query->addGroupField('solr_filegroupingid');
                return $this->grouped_files_query_response($this->client->query($query));
            } else {
                return $this->query_response($this->client->query($query));
            }
        } catch (\SolrClientException $ex) {
            debugging('Error executing the provided query: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            $this->queryerror = $ex->getMessage();
            return array();
        } catch (\SolrServerException $ex) {
            debugging('Error executing the provided query: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            $this->queryerror = $ex->getMessage();
            return array();
        }

    }

    /**
     * Prepares a new query by setting the query, start offset and rows to return.
     * @param SolrQuery $query
     * @param object $q Containing query and filters.
     */
    protected function set_query($query, $q) {

        // Set hightlighting.
        $query->setHighlight(true);
        foreach ($this->highlightfields as $field) {
            $query->addHighlightField($field);
        }
        $query->setHighlightFragsize(static::FRAG_SIZE);
        $query->setHighlightSimplePre('__');
        $query->setHighlightSimplePost('__');

        $query->setQuery($q);

        // A reasonable max.
        $query->setRows(\core_search\manager::MAX_RESULTS);
    }

    /**
     * Sets fields to be returned in the result.
     *
     * @param SolrQuery $query object.
     */
    public function add_fields($query) {
        $documentclass = $this->get_document_classname();
        $fields = array_keys($documentclass::get_default_fields_definition());
        foreach ($fields as $field) {
            $query->addField($field);
        }
    }

    /**
     * Finds the key common to both highlighing and docs array returned from response.
     * @param object $response containing results.
     */
    public function add_highlight_content($response) {
        $highlightedobject = $response->highlighting;
        foreach ($response->response->docs as $doc) {
            $x = $doc->id;
            $highlighteddoc = $highlightedobject->$x;
            $this->merge_highlight_field_values($doc, $highlighteddoc);
        }
    }

    /**
     * Adds the highlighting array values to docs array values.
     *
     * @throws \core_search\engine_exception
     * @param object $doc containing the results.
     * @param object $highlighteddoc containing the highlighted results values.
     */
    public function merge_highlight_field_values($doc, $highlighteddoc) {

        foreach ($this->highlightfields as $field) {
            if (!empty($doc->$field)) {

                // Check that the returned value is not an array. No way we can make this work with multivalued solr fields.
                if (is_array($doc->{$field})) {
                    throw new \core_search\engine_exception('multivaluedfield', 'search_solr', '', $field);
                }

                if (!empty($highlighteddoc->$field)) {
                    // Replace by the highlighted result.
                    $doc->$field = reset($highlighteddoc->$field);
                }
            }
        }
    }

    /**
     * Filters the response on Moodle side.
     *
     * @param object $queryresponse containing the response return from solr server.
     * @return array $results containing final results to be displayed.
     */
    public function query_response($queryresponse) {

        $response = $queryresponse->getResponse();
        $numgranted = 0;

        if (!$docs = $response->response->docs) {
            return array();
        }

        if (!empty($response->response->numFound)) {
            $this->add_highlight_content($response);

            // Iterate through the results checking its availability and whether they are available for the user or not.
            foreach ($docs as $key => $docdata) {
                if (!$searcharea = $this->get_search_area($docdata->areaid)) {
                    unset($docs[$key]);
                    continue;
                }

                $docdata = $this->standarize_solr_obj($docdata);

                $access = $searcharea->check_access($docdata['itemid']);
                switch ($access) {
                    case \core_search\manager::ACCESS_DELETED:
                        $this->delete_by_id($docdata['id']);
                        unset($docs[$key]);
                        break;
                    case \core_search\manager::ACCESS_DENIED:
                        unset($docs[$key]);
                        break;
                    case \core_search\manager::ACCESS_GRANTED:
                        $numgranted++;

                        // Add the doc.
                        $docs[$key] = $this->to_document($searcharea, $docdata);
                        break;
                }

                // This should never happen.
                if ($numgranted >= \core_search\manager::MAX_RESULTS) {
                    $docs = array_slice($docs, 0, \core_search\manager::MAX_RESULTS, true);
                    break;
                }
            }
        }

        return $docs;
    }


    /**
     * Processes grouped file results into documents, with attached matched files.
     *
     * @param object $queryresponse containing the response return from solr server
     * @return array $results containing final results to be displayed.
     */
    protected function grouped_files_query_response($queryresponse) {
        // TODO merge highlighting?
        $response = $queryresponse->getResponse();

        // If we can't find the grouping, or there are no matches in the grouping, return empty.
        if (!isset($response->grouped->solr_filegroupingid) || empty($response->grouped->solr_filegroupingid->matches)) {
            return array();
        }

        $docs = array();
        $numgranted = 0;

        // Each group represents a "master document" and will contain
        $groups = $response->grouped->solr_filegroupingid->groups;
        foreach ($groups as $group) {
            $groupid = $group->groupValue;
            $groupdocs = $group->doclist->docs;
            $firstdoc = reset($groupdocs);

            if (!$searcharea = $this->get_search_area($firstdoc->areaid)) {
                // Well, this is a problem.
                continue;
            }

            // Check for access.
            $access = $searcharea->check_access($firstdoc->itemid);
            switch ($access) {
                case \core_search\manager::ACCESS_DELETED:
                    // If deleted from Moodle, delete from index and then continue.
                    $this->delete_by_id($firstdoc->id);
                    continue 2;
                    break;
                case \core_search\manager::ACCESS_DENIED:
                    // This means we should just skip for the current user.
                    continue 2;
                    break;
            }
            $numgranted++;

            $maindoc = false;
            $filedocs = array();
            // Seperate the main document and any files returned.
            foreach ($groupdocs as $groupdoc) {
                if ($groupdoc->id == $groupid) {
                    $maindoc = $groupdoc;
                } else {
                    $filedocs[] = $groupdoc;
                }
            }

            if (!$maindoc) {
                // If we don't have the main doc, we need to produce it.
                // We prefer to build it locally for performance reasons, rather than hitting solr again.
                $maindoc = $searcharea->get_document_for_id($firstdoc->itemid);
                $docdata = $maindoc->export_for_engine();
            } else {
                $docdata = $this->standarize_solr_obj($maindoc);
            }
            $doc = $this->to_document($searcharea, $docdata);

            // Now we need to attach the result files to the doc.
            foreach ($filedocs as $filedoc) {
                $doc->add_stored_file($filedoc->solr_fileid);
            }

            $docs[] = $doc;
        }

        // This should never happen.
        if ($numgranted >= \core_search\manager::MAX_RESULTS) {
            $docs = array_slice($docs, 0, \core_search\manager::MAX_RESULTS, true);
        }

        return $docs;
    }

    /**
     * Returns a standard php array from a \SolrObject instance.
     *
     * @param \SolrObject $obj
     * @return array The returned document as an array.
     */
    public function standarize_solr_obj(\SolrObject $obj) {
        $properties = $obj->getPropertyNames();

        $docdata = array();
        foreach($properties as $name) {
            // http://php.net/manual/en/solrobject.getpropertynames.php#98018.
            $name = trim($name);
            $docdata[$name] = $obj->offsetGet($name);
        }
        return $docdata;
    }

    /**
     * Adds a document to the search engine.
     *
     * This does not commit to the search engine.
     *
     * @param document $document
     * @param bool     $fileindexing True if file indexing is to be used
     * @return void
     */
    public function add_document($document, $fileindexing = false) {

        $docdata = $document->export_for_engine();
        switch ($docdata['type']) {
            case \core_search\manager::TYPE_TEXT:
                $this->add_text_document($docdata);
                break;
            default:
                return false;
        }

        if ($fileindexing) {
            // This will take care of updating all attached files in the index.
            $this->process_document_files($document);
        }

        return true;
    }

    /**
     * Get index the document, ensuring the index matches the current document files.
     *
     * @param document $document
     */
    protected function process_document_files($document) {
        if (!$this->file_indexing_enabled()) {
            return;
        }

        // Get the attached files and currently indexed files.
        $files = $document->get_files();

        // If this isn't a new document, we need to check the exiting indexed files.
        if (!$document->get_is_new()) {
            $indexedfiles = $this->get_indexed_files($document);

            // Go through each indexed file, we want to not index any stored ones, delete any missing ones.
            foreach ($indexedfiles as $indexedfile) {
                $fileid = $indexedfile->get('solr_fileid');
                if (isset($files[$fileid])) {
                    if ($indexedfile->get('modified') < $files[$fileid]->get_timemodified()) {
                        // If the file has been modified since it was indexed, just leave it for re-indexing.
                        continue;
                    }
                    // Filelib does not guarantee time modified is updated, so we will check important values.
                    if (strcmp($indexedfile->get('title'), $files[$fileid]->get_filename()) !== 0) {
                        // Check if the filename has changed, since it is an indexed field.
                        continue;
                    }
                    if ($indexedfile->get('solr_filecontenthash') != $files[$fileid]->get_contenthash()) {
                        // If the stored content hash doesn't match, update the indexing.
                        continue;
                    }

                    // If the file is already indexed, we can just remove it from the files array and skip it.
                    debugging('Skipping file '.$fileid, DEBUG_DEVELOPER);
                    unset($files[$fileid]);
                } else {
                    // This means we have found a file that is no longer attached, so we need to delete from the index.
                    debugging('Deleting file '.$indexedfile->get('id'), DEBUG_DEVELOPER);
                    $this->get_search_client()->deleteById($indexedfile->get('id'));
                }
            }
        }

        // Now we can actually index all the remaining files.
        foreach ($files as $file) {
            debugging('Indexing file '.$file->get_id(), DEBUG_DEVELOPER);
            $this->add_stored_file($document, $file);
        }
    }

    /**
     * Get all the currently indexed files for a particular document.
     *
     * @param document $document
     * @return document[] An array of documents representing indexed files.
     */
    protected function get_indexed_files($document) {
        // Build a custom query that will get any document files that are in our solr_filegroupingid.
        $query = new \SolrQuery();
        $this->set_query($query, '*');
        $this->add_fields($query);

        $query->addFilterQuery('{!cache=false}solr_filegroupingid:(' . $document->get('id') . ')');
        $query->addFilterQuery('{!cache=false}type:(' . \core_search\manager::TYPE_FILE. ')');

        try {
            return $this->query_response($this->get_search_client()->query($query));
        } catch (\SolrClientException $ex) {
            debugging('Error executing the provided query: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            $this->queryerror = $ex->getMessage();
            return array();
        } catch (\SolrServerException $ex) {
            debugging('Error executing the provided query: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            $this->queryerror = $ex->getMessage();
            return array();
        }
    }

    /**
     * Adds a file to the search engine.
     *
     * @param array $filedoc
     * @return void
     */
    protected function add_stored_file($document, $storedfile) {
        // TODO filter files by type and size.

        $filedoc = $document->export_file_for_engine($storedfile);

        if ($filedoc['type'] != \core_search\manager::TYPE_FILE) {
            throw new \core_search\engine_exception('enginewrongtypefile', 'search');
        }

        $curl = $this->get_curl_object();

        // TODO build this url elsewhere. Used by schema too.
        $url = $this->config->server_hostname;
        $url .= ':'.(!empty($this->config->server_port) ? $this->config->server_port : '8983');
        $url .= '/solr/' . $this->config->indexname;
        $url = new \moodle_url($url.'/update/extract');

        // Copy each key to the url with literal.
        foreach ($filedoc as $key => $value) {
            $url->param('literal.'.$key, $value);
        }

        // This sets the true filename for Tika.
        $url->param('resource.name', $storedfile->get_filename());

        // Tell Solr/Tika the mime type.
        $url->param('stream.type', $storedfile->get_mimetype());

        // TODO - parse results for error, and catch/throw exceptions.
        $strurl = $url->out(false);
        $rr = $curl->post($strurl, array('myfile' => $storedfile));

        print "<pre>";print_r($rr);print "</pre>";
    }

    /**
     * Adds a text document to the search engine.
     *
     * @param array $filedoc
     * @return void
     */
    protected function add_text_document($doc) {
        $solrdoc = new \SolrInputDocument();
        foreach ($doc as $field => $value) {
            $solrdoc->addField($field, $value);
        }

        try {
            $result = $this->get_search_client()->addDocument($solrdoc, true, static::AUTOCOMMIT_WITHIN);
        } catch (\SolrClientException $e) {
            debugging('Solr client error adding document with id ' . $doc['id'] . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        } catch (\SolrServerException $e) {
            // We only use the first line of the message, as it's a fully java stacktrace behind it.
            $msg = strtok($e->getMessage(), "\n");
            debugging('Solr server error adding document with id ' . $doc['id'] . ': ' . $msg, DEBUG_DEVELOPER);
        }
    }

    /**
     * Commits all pending changes.
     *
     * @return void
     */
    protected function commit() {
        $this->get_search_client()->commit();
    }

    /**
     * Do any area cleanup needed, and do anything to confirm contents.
     *
     * Return false to prevent the search area completed time and stats from being updated.
     *
     * @param \core_search\area\base $searcharea The search area that was complete
     * @param int $numdocs The number of documents that were added to the index
     * @param bool $fullindex True if a full index is being performed
     * @return bool True means that data is considered indexed
     */
    public function area_index_complete($searcharea, $numdocs = 0, $fullindex = false) {
        $this->commit();

        return true;
    }

    /**
     * Defragments the index.
     *
     * @return void
     */
    public function optimize() {
        $this->get_search_client()->optimize(1, true, false);
    }

    /**
     * Return true if file indexing is supported and enabled. False otherwise.
     *
     * @return bool
     */
    public function file_indexing_enabled() {
        // TODO - add actual settings.
        return true;
    }

    /**
     * Deletes the specified document.
     *
     * @param string $id The document id to delete
     * @return void
     */
    public function delete_by_id($id) {
        // We need to make sure we delete the item and all related files, whichc an be done with solr_filegroupingid.
        $this->get_search_client()->deleteByQuery('solr_filegroupingid:' . $id);
        $this->commit();
    }

    /**
     * Delete all area's documents.
     *
     * @param string $areaid
     * @return void
     */
    public function delete($areaid = null) {
        if ($areaid) {
            $this->get_search_client()->deleteByQuery('areaid:' . $areaid);
        } else {
            $this->get_search_client()->deleteByQuery('*:*');
        }
        $this->commit();
    }

    /**
     * Pings the Solr server using search_solr config
     *
     * @return true|string Returns true if all good or an error string.
     */
    public function is_server_ready() {

        if (empty($this->config->server_hostname) || empty($this->config->indexname)) {
            return 'No solr configuration found';
        }

        if (!$this->client = $this->get_search_client(false)) {
            return get_string('engineserverstatus', 'search');
        }

        try {
            @$this->client->ping();
        } catch (\SolrClientException $ex) {
            return 'Solr client error: ' . $ex->getMessage();
        } catch (\SolrServerException $ex) {
            return 'Solr server error: ' . $ex->getMessage();
        }

        // Check that setup schema has already run.
        try {
            $schema = new \search_solr\schema();
            $schema->validate_setup();
        } catch (\moodle_exception $e) {
            return $e->getMessage();
        }

        return true;
    }

    /**
     * Checks if the PHP Solr extension is available.
     *
     * @return bool
     */
    public function is_installed() {
        return function_exists('solr_get_version');
    }

    /**
     * Returns the solr client instance.
     *
     * @throws \core_search\engine_exception
     * @param bool $triggerexception
     * @return \SolrClient
     */
    protected function get_search_client($triggerexception = true) {

        // Type comparison as it is set to false if not available.
        if ($this->client !== null) {
            return $this->client;
        }

        $options = array(
            'hostname' => $this->config->server_hostname,
            'path'     => '/solr/' . $this->config->indexname,
            'login'    => !empty($this->config->server_username) ? $this->config->server_username : '',
            'password' => !empty($this->config->server_password) ? $this->config->server_password : '',
            'port'     => !empty($this->config->server_port) ? $this->config->server_port : '',
            'issecure' => !empty($this->config->secure) ? $this->config->secure : '',
            'ssl_cert' => !empty($this->config->ssl_cert) ? $this->config->ssl_cert : '',
            'ssl_cert_only' => !empty($this->config->ssl_cert_only) ? $this->config->ssl_cert_only : '',
            'ssl_key' => !empty($this->config->ssl_key) ? $this->config->ssl_key : '',
            'ssl_password' => !empty($this->config->ssl_keypassword) ? $this->config->ssl_keypassword : '',
            'ssl_cainfo' => !empty($this->config->ssl_cainfo) ? $this->config->ssl_cainfo : '',
            'ssl_capath' => !empty($this->config->ssl_capath) ? $this->config->ssl_capath : '',
            'timeout' => !empty($this->config->server_timeout) ? $this->config->server_timeout : '30'
        );

        $this->client = new \SolrClient($options);

        if ($this->client === false && $triggerexception) {
            throw new \core_search\engine_exception('engineserverstatus', 'search');
        }

        return $this->client;
    }

    /**
     * Returns a curl object for conntecting to solr.
     *
     * @return \curl
     */
    public function get_curl_object() {
        if (!is_null($this->curl)) {
            return $this->curl;
        }

        $this->curl = new \curl();

        // TODO - we need handle all the SSL and cert options here.

        if (!empty($this->config->server_username) && !empty($this->config->server_password)) {
            $authorization = $this->config->server_username . ':' . $this->config->server_password;
            $this->curl->setHeader('Authorization', 'Basic ' . base64_encode($authorization));
        }

        return $this->curl;
    }
}
