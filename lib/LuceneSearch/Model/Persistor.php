<?php

namespace LuceneSearch\Model;

class Persistor
{
    /**
     * @var string
     */
    private $db = NULL;

    /**
     * @var array
     */
    private $data = [];

    /**
     * @var array
     */
    public $options = [
        'dir'               => PIMCORE_TEMPORARY_DIRECTORY . '/',
        'ext'               => '.tmp',
        'gzip'              => FALSE,
        'cache'             => TRUE,
        'swap_memory_limit' => 1048576
    ];

    public function __construct($database, $options = [])
    {
        if (!preg_match('/^([A-Za-z0-9_-]+)$/', $database)) {
            throw new \Exception('Invalid characters in database name');
        }

        // Set current database
        $this->db = $database;

        // Set options
        if (!empty($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * Set flintstone options
     *
     * @param array $options an array of options
     *
     * @return void
     */
    public function setOptions($options)
    {
        foreach ($options as $key => $value) {
            $this->options[$key] = $value;
        }
    }

    /**
     * @throws \Exception
     */
    public function setupDatabase()
    {
        if (empty($this->data)) {

            $dir = rtrim($this->options['dir'], '/\\') . DIRECTORY_SEPARATOR;

            if (!is_dir($dir)) {
                throw new \Exception($dir . ' is not a valid directory');
            }

            $ext = $this->options['ext'];
            if (substr($ext, 0, 1) !== '.') {
                $ext = '.' . $ext;
            }
            if ($this->options['gzip'] === TRUE && substr($ext, -3) !== '.gz') {
                $ext .= '.gz';
            }
            $this->data['file'] = $dir . $this->db . $ext;
            $this->data['file_tmp'] = $dir . $this->db . '_tmp' . $ext;
            $this->data['cache'] = [];

            // Create database
            if (!file_exists($this->data['file'])) {
                if (($fp = $this->openFile($this->data['file'], 'wb')) !== FALSE) {
                    @fclose($fp);
                    @chmod($this->data['file'], 0777);
                    clearstatcache();
                } else {
                    throw new \Exception('Could not create database ' . $this->db);
                }
            }

            // Check file is readable
            if (!is_readable($this->data['file'])) {
                throw new \Exception('Could not read database ' . $this->db);
            }

            // Check file is writable
            if (!is_writable($this->data['file'])) {
                throw new \Exception('Could not write to database ' . $this->db);
            }
        }
    }

    /**
     * @param $file
     * @param $mode
     *
     * @return resource
     */
    private function openFile($file, $mode)
    {
        if ($this->options['gzip'] === TRUE) {
            $file = 'compress.zlib://' . $file;
        }

        return @fopen($file, $mode);
    }

    /**
     * @param $key
     *
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    private function getKey($key)
    {
        $data = FALSE;

        if ($this->options['cache'] === TRUE && array_key_exists($key, $this->data['cache'])) {
            return $this->data['cache'][$key];
        }

        if (($fp = $this->openFile($this->data['file'], 'rb')) !== FALSE) {

            @flock($fp, LOCK_SH);

            while (($line = fgets($fp)) !== FALSE) {

                $line = rtrim($line);
                $pieces = explode('=', $line);

                if ($pieces[0] == $key) {

                    if (count($pieces) > 2) {
                        array_shift($pieces);
                        $data = implode('=', $pieces);
                    } else {
                        $data = $pieces[1];
                    }

                    $data = unserialize($data);

                    $data = $this->preserveLines($data, TRUE);

                    if ($this->options['cache'] === TRUE) {
                        $this->data['cache'][$key] = $data;
                    }

                    break;
                }
            }

            @flock($fp, LOCK_UN);
            @fclose($fp);
        } else {
            throw new \Exception('Could not open database ' . $this->db);
        }

        return $data;
    }

    /**
     * @param $key
     * @param $data
     *
     * @return bool
     * @throws \Exception
     */
    private function replaceKey($key, $data)
    {
        $swap = TRUE;
        $contents = '';
        $origData = NULL;

        if ($this->options['swap_memory_limit'] > 0) {
            clearstatcache();
            if (filesize($this->data['file']) <= $this->options['swap_memory_limit']) {
                $swap = FALSE;
            }
        }

        if ($data !== FALSE) {

            if ($this->options['cache'] === TRUE) {
                $origData = $data;
            }

            $data = $this->preserveLines($data, FALSE);
            $data = serialize($data);
        }

        if ($swap) {
            if (($tp = $this->openFile($this->data['file_tmp'], 'ab')) !== FALSE) {
                @flock($tp, LOCK_EX);
            } else {
                throw new \Exception('Could not create temporary database for ' . $this->db);
            }
        }

        if (($fp = $this->openFile($this->data['file'], 'rb')) !== FALSE) {

            @flock($fp, LOCK_SH);

            while (($line = fgets($fp)) !== FALSE) {

                $pieces = explode('=', $line);
                if ($pieces[0] == $key) {

                    if ($data === FALSE) {
                        continue;
                    }

                    $line = $key . '=' . $data . "\n";

                    if ($this->options['cache'] === TRUE) {
                        $this->data['cache'][$key] = $origData;
                    }
                }

                if ($swap) {

                    $fwrite = @fwrite($tp, $line);
                    if ($fwrite === FALSE) {
                        throw new \Exception('Could not write to temporary database ' . $this->db);
                    }

                } else {
                    $contents .= $line;
                }
            }

            @flock($fp, LOCK_UN);
            @fclose($fp);

            if ($swap) {

                @flock($tp, LOCK_UN);
                @fclose($tp);

                if (!@unlink($this->data['file'])) {
                    throw new \Exception('Could not remove old database ' . $this->db);
                }

                if (!@rename($this->data['file_tmp'], $this->data['file'])) {
                    throw new \Exception('Could not rename temporary database ' . $this->db);
                }

                @chmod($this->data['file'], 0777);
            } else {

                if (($fp = $this->openFile($this->data['file'], 'wb')) !== FALSE) {

                    @flock($fp, LOCK_EX);
                    $fwrite = @fwrite($fp, $contents);
                    @flock($fp, LOCK_UN);
                    @fclose($fp);

                    unset($contents);

                    if ($fwrite === FALSE) {
                        throw new \Exception('Could not write to database ' . $this->db);
                    }
                } else {
                    throw new \Exception('Could not open database ' . $this->db);
                }
            }
        } else {
            throw new \Exception('Could not open database ' . $this->db);
        }

        return TRUE;
    }

    /**
     * @param $key
     * @param $data
     *
     * @return bool
     * @throws \Exception
     */
    private function setKey($key, $data)
    {
        if ($this->getKey($key) !== FALSE) {
            return $this->replaceKey($key, $data);
        }

        $origData = NULL;

        if ($this->options['cache'] === TRUE) {
            $origData = $data;
        }

        $data = $this->preserveLines($data, FALSE);
        $data = serialize($data);

        if (($fp = $this->openFile($this->data['file'], 'ab')) !== FALSE) {

            @flock($fp, LOCK_EX);

            // Set line, we don't use PHP_EOL to keep it cross-platform compatible
            $line = $key . '=' . $data . "\n";
            $fwrite = @fwrite($fp, $line);
            @flock($fp, LOCK_UN);
            @fclose($fp);

            if ($fwrite === FALSE) {
                throw new \Exception('Could not write to database ' . $this->db);
            }

            if ($this->options['cache'] === TRUE) {
                $this->data['cache'][$key] = $origData;
            }
        } else {
            throw new \Exception('Could not open database ' . $this->db);
        }

        return TRUE;
    }

    /**
     * Delete a key from the database
     *
     * @param string $key the key
     *
     * @return boolean successful delete
     */
    private function deleteKey($key)
    {
        if ($this->getKey($key) !== FALSE) {

            if ($this->replaceKey($key, FALSE)) {

                if ($this->options['cache'] === TRUE && array_key_exists($key, $this->data['cache'])) {
                    unset($this->data['cache'][$key]);
                }

                return TRUE;
            }
        }

        return FALSE;
    }

    private function flushDatabase()
    {
        if (($fp = $this->openFile($this->data['file'], 'wb')) !== FALSE) {

            @fclose($fp);

            if ($this->options['cache'] === TRUE) {
                $this->data['cache'] = [];
            }
        } else {
            throw new \Exception('Could not open database ' . $this->db);
        }

        return TRUE;
    }

    private function preserveLines($data, $reverse)
    {
        if ($reverse) {
            $from = ["\\n", "\\r"];
            $to = ["\n", "\r"];
        } else {
            $from = ["\n", "\r"];
            $to = ["\\n", "\\r"];
        }

        if (is_string($data)) {
            $data = str_replace($from, $to, $data);
        } elseif (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->preserveLines($value, $reverse);
            }
        }

        return $data;
    }

    private function isValidKey($key)
    {
        $len = strlen($key);

        if ($len < 1) {
            throw new \Exception('No key has been set');
        }

        if ($len > 50) {
            throw new \Exception('Maximum key length is 50 characters');
        }

        if (!preg_match('/^([A-Za-z0-9_]+)$/', $key)) {
            throw new \Exception('Invalid characters in key');
        }

        return TRUE;
    }

    private function isValidData($data)
    {
        if (!is_string($data) && !is_int($data) && !is_float($data) && !is_array($data)) {
            throw new \Exception('Invalid data type');
        }

        return TRUE;
    }

    /**
     * Get a key from the database
     *
     * @param string $key the key
     *
     * @return mixed the data
     */
    public function get($key)
    {
        $this->setupDatabase();

        if ($this->isValidKey($key)) {
            return $this->getKey($key);
        }

        return FALSE;
    }

    /**
     * Set a key to store in the database
     *
     * @param string $key  the key
     * @param mixed  $data the data to store
     *
     * @return boolean successful set
     */
    public function set($key, $data)
    {
        $this->setupDatabase();

        if ($this->isValidKey($key) && $this->isValidData($data)) {
            return $this->setKey($key, $data);
        }

        return FALSE;
    }

    /**
     * Delete a key from the database
     *
     * @param string $key the key
     *
     * @return boolean successful delete
     */
    public function delete($key)
    {
        $this->setupDatabase();

        if ($this->isValidKey($key)) {
            return $this->deleteKey($key);
        }

        return FALSE;
    }

    /**
     * Flush the database
     * @return boolean successful flush
     */
    public function flush()
    {
        $this->setupDatabase();

        return $this->flushDatabase();
    }
}