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
 * The library file for the memcache cache store.
 *
 * This file is part of the memcache cache store, it contains the API for interacting with an instance of the store.
 *
 * @package    cachestore_memcache
 * @copyright  2012 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The memcache store class.
 *
 * (Not to be confused with memcached store)
 *
 * Configuration options:
 *      servers:        string: host:port:weight , ...
 *
 * @copyright  2012 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cachestore_memcachecluster extends cache_store implements cache_is_configurable {

    /**
     * The name of the store
     * @var store
     */
    protected $name;

    /**
     * The memcache connection once established.
     * @var Memcache
     */
    protected $getconnection;

    /**
     * The memcache connection once established.
     * @var Memcache
     */
    protected $setconnections;

    /**
     * Key prefix for this memcache.
     * @var string
     */
    protected $prefix;

    /**
     * An array of servers to use in the connection args.
     * @var array
     */
    protected $getservers = array();

    /**
     * An array of servers to use in the connection args.
     * @var array
     */
    protected $setservers = array();

    /**
     * An array of options used when establishing the connection.
     * @var array
     */
    protected $options = array();

    /**
     * Set to true when things are ready to be initialised.
     * @var bool
     */
    protected $isready = false;

    /**
     * Set to true once this store instance has been initialised.
     * @var bool
     */
    protected $isinitialised = false;

    /**
     * The cache definition this store was initialised for.
     * @var cache_definition
     */
    protected $definition;

    /**
     * Default prefix for key names.
     * @var string
     */
    const DEFAULT_PREFIX = 'mdl_';

    /**
     * Constructs the store instance.
     *
     * Noting that this function is not an initialisation. It is used to prepare the store for use.
     * The store will be initialised when required and will be provided with a cache_definition at that time.
     *
     * @param string $name
     * @param array $configuration
     */
    public function __construct($name, array $configuration = array()) {
        $ready = false;

        $this->name = $name;
        if (!array_key_exists('getservers', $configuration) || empty($configuration['getservers'])) {
            // Nothing configured.
            return;
        }

        if (!array_key_exists('setservers', $configuration) || empty($configuration['setservers'])) {
            // Nothing configured.
            return;
        }
        if (!is_array($configuration['getservers'])) {
            $configuration['getservers'] = array($configuration['getservers']);
        }
        foreach ($configuration['getservers'] as $getserver) {
            if (!is_array($getserver)) {
                $getserver = explode(':', $getserver, 3);
            }
            if (!array_key_exists(1, $getserver)) {
                $getserver[1] = 11211;
                $getserver[2] = 100;
            } else if (!array_key_exists(2, $getserver)) {
                $getserver[2] = 100;
            }
            $this->getservers[] = $getserver;
        }

        $this->getconnection = new Memcache;
        foreach ($this->getservers as $getserver) {
            $this->getconnection->addServer($getserver[0], $getserver[1], true, $getserver[2]);
        }
        // Test the connection to the pool of servers.
        $ready = @$this->getconnection->set($this->parse_key('ping'), 'ping', MEMCACHE_COMPRESSED, 1);


        if (!is_array($configuration['setservers'])) {
            $configuration['setservers'] = array($configuration['setservers']);
        }
        foreach ($configuration['setservers'] as $setserver) {
            if (!is_array($setserver)) {
                $setserver = explode(':', $setserver, 3);
            }
            if (!array_key_exists(1, $setserver)) {
                $setserver[1] = 11211;
                $setserver[2] = 100;
            } else if (!array_key_exists(2, $setserver)) {
                $setserver[2] = 100;
            }
            $this->setservers[] = $setserver;
        }
        if (empty($configuration['prefix'])) {
            $this->prefix = self::DEFAULT_PREFIX;
        } else {
            $this->prefix = $configuration['prefix'];
        }

        $this->setconnections = array();
        foreach ($this->setservers as $setserver) {
            $conn = new Memcache;
            $conn->addServer($setserver[0], $setserver[1], true, $setserver[2]);
            $ready && (@$conn->set($this->parse_key('ping'), 'ping', MEMCACHE_COMPRESSED, 1));
            $this->setconnections[] = $conn;
        }
        // Test the connection to the pool of servers.
        $this->isready = $ready;
    }

    // No change.
    /**
     * Initialises the cache.
     *
     * Once this has been done the cache is all set to be used.
     *
     * @param cache_definition $definition
     */
    public function initialise(cache_definition $definition) {
        if ($this->is_initialised()) {
            throw new coding_exception('This memcache instance has already been initialised.');
        }
        $this->definition = $definition;
        $this->isinitialised = true;
    }

    // No Change.
    /**
     * Returns true once this instance has been initialised.
     *
     * @return bool
     */
    public function is_initialised() {
        return ($this->isinitialised);
    }

    // No change;
    /**
     * Returns true if this store instance is ready to be used.
     * @return bool
     */
    public function is_ready() {
        return $this->isready;
    }

    // No change;
    /**
     * Returns true if the store requirements are met.
     *
     * @return bool
     */
    public static function are_requirements_met() {
        return class_exists('Memcache');
    }

    // No change/
    /**
     * Returns true if the given mode is supported by this store.
     *
     * @param int $mode One of cache_store::MODE_*
     * @return bool
     */
    public static function is_supported_mode($mode) {
        return ($mode === self::MODE_APPLICATION || $mode === self::MODE_SESSION);
    }

    // No change.
    /**
     * Returns the supported features as a combined int.
     *
     * @param array $configuration
     * @return int
     */
    public static function get_supported_features(array $configuration = array()) {
        return self::SUPPORTS_NATIVE_TTL;
    }

    // No change.
    /**
     * Returns false as this store does not support multiple identifiers.
     * (This optional function is a performance optimisation; it must be
     * consistent with the value from get_supported_features.)
     *
     * @return bool False
     */
    public function supports_multiple_identifiers() {
        return false;
    }

    // No change.
    /**
     * Returns the supported modes as a combined int.
     *
     * @param array $configuration
     * @return int
     */
    public static function get_supported_modes(array $configuration = array()) {
        return self::MODE_APPLICATION + self::MODE_SESSION;
    }

    // No change.
    /**
     * Parses the given key to make it work for this memcache backend.
     *
     * @param string $key The raw key.
     * @return string The resulting key.
     */
    protected function parse_key($key) {
        if (strlen($key) > 245) {
            $key = '_sha1_'.sha1($key);
        }
        $key = $this->prefix . $key;
        return $key;
    }

    /**
     * Retrieves an item from the cache store given its key.
     *
     * @param string $key The key to retrieve
     * @return mixed The data that was associated with the key, or false if the key did not exist.
     */
    public function get($key) {
        return $this->getconnection->get($this->parse_key($key));
    }

    /**
     * Retrieves several items from the cache store in a single transaction.
     *
     * If not all of the items are available in the cache then the data value for those that are missing will be set to false.
     *
     * @param array $keys The array of keys to retrieve
     * @return array An array of items from the cache. There will be an item for each key, those that were not in the store will
     *      be set to false.
     */
    public function get_many($keys) {
        $mkeys = array();
        foreach ($keys as $key) {
            $mkeys[$key] = $this->parse_key($key);
        }
        $result = $this->getconnection->get($mkeys);
        if (!is_array($result)) {
            $result = array();
        }
        $return = array();
        foreach ($mkeys as $key => $mkey) {
            if (!array_key_exists($mkey, $result)) {
                $return[$key] = false;
            } else {
                $return[$key] = $result[$mkey];
            }
        }
        return $return;
    }

    /**
     * Sets an item in the cache given its key and data value.
     *
     * @param string $key The key to use.
     * @param mixed $data The data to set.
     * @return bool True if the operation was a success false otherwise.
     */
    public function set($key, $data) {
        $ret = true;
        foreach ($this->setconnections as $conn) {
            $ret = $conn->set($this->parse_key($key), $data, MEMCACHE_COMPRESSED, $this->definition->get_ttl()) && $ret;
        }
        return $ret;
    }

    /**
     * Sets many items in the cache in a single transaction.
     *
     * @param array $keyvaluearray An array of key value pairs. Each item in the array will be an associative array with two
     *      keys, 'key' and 'value'.
     * @return int The number of items successfully set. It is up to the developer to check this matches the number of items
     *      sent ... if they care that is.
     */
    public function set_many(array $keyvaluearray) {
        $count = 0;
        foreach ($keyvaluearray as $pair) {
            $success = true;
            foreach ($this->setconnections as $conn) {
                $sucess = $conn->set($this->parse_key($pair['key']), $pair['value'], MEMCACHE_COMPRESSED, $this->definition->get_ttl()) && $success;
            }
            if ($success) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Deletes an item from the cache store.
     *
     * @param string $key The key to delete.
     * @return bool Returns true if the operation was a success, false otherwise.
     */
    public function delete($key) {
        $ret = true;
        foreach ($this->setconnections as $conn) {
            $ret = $conn->delete($this->parse_key($key)) && $ret;
        }
        return $ret;
    }

    /**
     * Deletes several keys from the cache in a single action.
     *
     * @param array $keys The keys to delete
     * @return int The number of items successfully deleted.
     */
    public function delete_many(array $keys) {
        $count = 0;
        foreach ($keys as $key) {
            if ($this->delete($key)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Purges the cache deleting all items within it.
     *
     * @return boolean True on success. False otherwise.
     */
    public function purge() {
        if ($this->isready) {
            foreach ($this->setconnections as $conn) {
                $conn->flush();
            }
        }

        return true;
    }

    /**
     * Given the data from the add instance form this function creates a configuration array.
     *
     * @param stdClass $data
     * @return array
     */
    public static function config_get_configuration_array($data) {
        $lines = explode("\n", $data->getservers);
        $getservers = array();
        foreach ($lines as $line) {
            $line = trim($line, ':');
            $line = trim($line);
            $getservers[] = explode(':', $line, 3);
        }
        $lines = explode("\n", $data->setservers);
        $setservers = array();
        foreach ($lines as $line) {
            $line = trim($line, ':');
            $line = trim($line);
            $setservers[] = explode(':', $line, 3);
        }
        return array(
            'getservers' => $getservers,
            'setservers' => $setservers,
            'prefix' => $data->prefix,
        );
    }

    /**
     * Allows the cache store to set its data against the edit form before it is shown to the user.
     *
     * @param moodleform $editform
     * @param array $config
     */
    public static function config_set_edit_form_data(moodleform $editform, array $config) {
        $data = array();
        if (!empty($config['getservers'])) {
            $getservers = array();
            foreach ($config['getservers'] as $getserver) {
                $getservers[] = join(":", $getserver);
            }
            $data['getservers'] = join("\n", $getservers);
        }
        if (!empty($config['setservers'])) {
            $setservers = array();
            foreach ($config['setservers'] as $setserver) {
                $setservers[] = join(":", $setserver);
            }
            $data['setservers'] = join("\n", $setservers);
        }
        if (!empty($config['prefix'])) {
            $data['prefix'] = $config['prefix'];
        } else {
            $data['prefix'] = self::DEFAULT_PREFIX;
        }

        $editform->set_data($data);
    }

    /**
     * Performs any necessary clean up when the store instance is being deleted.
     */
    public function instance_deleted() {
        if ($this->getconnection) {
            $getconnection = $this->getconnection;
        } else {
            $getconnection = new Memcache;
            foreach ($this->getservers as $getserver) {
                $getconnection->addServer($getserver[0], $getserver[1], true, $getserver[2]);
            }
        }
        @$getconnection->flush();
        unset($getconnection);
        unset($this->getconnection);
// TODO!!
        if ($this->setconnections) {
            $setconnection = $this->setconnection;
        } else {
            $setconnection = new Memcache;
            foreach ($this->setservers as $setserver) {
                $setconnection->addServer($setserver[0], $setserver[1], true, $setserver[2]);
            }
        }
        @$setconnection->flush();
        unset($setconnection);
        unset($this->setconnection);
    }

    /**
     * Generates an instance of the cache store that can be used for testing.
     *
     * @param cache_definition $definition
     * @return cachestore_memcache|false
     */
    public static function initialise_test_instance(cache_definition $definition) {
        if (!self::are_requirements_met()) {
            return false;
        }

        $config = get_config('cachestore_memcache');
        if (empty($config->testservers)) {
            return false;
        }

        $configuration = array();
        $configuration['servers'] = explode("\n", $config->testservers);

        $store = new cachestore_memcache('Test memcache', $configuration);
        $store->initialise($definition);

        return $store;
    }

    /**
     * Returns the name of this instance.
     * @return string
     */
    public function my_name() {
        return $this->name;
    }
}
