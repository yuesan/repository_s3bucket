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
 * This plugin is used to access s3bucket files
 *
 * @package    repository_s3bucket
 * @copyright  2015 Renaat Debleu (www.eWallah.net) (based on work by Dongsheng Cai)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot . '/repository/lib.php');
require_once($CFG->dirroot . '/repository/s3/S3.php');

/**
 * This is a repository class used to browse a Amazon S3 bucket.
 *
 * @package    repository_s3bucket
 * @copyright  2015 Renaat Debleu (www.eWallah.net) (based on work by Dongsheng Cai)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_s3bucket extends repository {

    /**
     * Get S3 file list
     *
     * @param string $path this parameter can a folder name, or a identification of folder
     * @param string $page the page number of file list
     * @return array the list of files, including some meta infomation
     */
    public function get_listing($path = '', $page = '') {
        global $OUTPUT;
        $s = $this->create_s3();
        $bucket = $this->get_option('bucket_name');

        $list = array();
        $list['list'] = array();
        $list['path'] = array(array('name' => $bucket, 'path' => ''));
        $list['manage'] = false;
        $list['dynload'] = true;
        $list['nologin'] = true;
        $list['nosearch'] = true;
        $files = array();
        $folders = array();

        try {
            $contents = $s->getBucket($bucket, $path, null, null, '/', true);
        } catch (S3Exception $e) {
            throw new moodle_exception(
                'errorwhilecommunicatingwith',
                'repository',
                '',
                $this->get_name(),
                $e->getMessage()
            );
        }
        foreach ($contents as $object) {
            if (isset($object['prefix'])) {
                $title = rtrim($object['prefix'], '/');
            } else {
                $title = $object['name'];
            }
            if (strlen($path) > 0) {
                $title = substr($title, strlen($path));
                if (empty($title) && !is_numeric($title)) {
                    continue;
                }
            }
            if (isset($object['prefix'])) {
                $folders[] = array(
                    'title' => $title,
                    'children' => array(),
                    'thumbnail' => $OUTPUT->pix_url(file_folder_icon(90))->out(false),
                    'path' => $object['prefix']
                );
            } else {
                $files[] = array(
                    'title' => $title,
                    'size' => $object['size'],
                    'datemodified' => $object['time'],
                    'source' => $object['name'],
                    'thumbnail' => $OUTPUT->pix_url(file_extension_icon($title, 90))->out(false)
                );
            }
        }
        $list['list'] = array_merge($folders, $files);
        return $list;
    }

    /**
     * Download S3 files to moodle
     *
     * @param string $filepath
     * @param string $file The file path in moodle
     * @return array The local stored path
     */
    public function get_file($filepath, $file = '') {
        $path = $this->prepare_file($file);
        $s = $this->create_s3();
        $bucket = $this->get_option('bucket_name');
        try {
            $s->getObject($bucket, $filepath, $path);
        } catch (S3Exception $e) {
            throw new moodle_exception(
                'errorwhilecommunicatingwith',
                'repository',
                '',
                $this->get_name(),
                $e->getMessage()
            );
        }
        return array('path' => $path);
    }

    /**
     * Return the source information
     *
     * @param stdClass $filepath
     * @return string
     */
    public function get_file_source_info($filepath) {
        return 'Amazon S3: ' . $this->get_option('bucket_name') . '/' . $filepath;
    }

    /**
     * S3 doesn't require login
     *
     * @return bool
     */
    public function check_login() {
        return true;
    }

    /**
     * S3 doesn't provide search
     *
     * @return bool
     */
    public function global_search() {
        return false;
    }

    /**
     * Return names of the instance options.
     * By default: no instance option name
     *
     * @return array
     */
    public static function get_instance_option_names() {
        return array('access_key', 'secret_key', 'endpoint', 'bucket_name');
    }

    /**
     * Edit/Create Instance Settings Moodle form
     *
     * @param moodleform $mform Moodle form (passed by reference)
     */
    public static function instance_config_form($mform) {
        parent::instance_config_form($mform);
        $strrequired = get_string('required');
        $endpointselect = array(
            "s3.amazonaws.com" => "s3.amazonaws.com",
            "s3-external-1.amazonaws.com" => "s3-external-1.amazonaws.com",
            "s3-us-west-2.amazonaws.com" => "s3-us-west-2.amazonaws.com",
            "s3-us-west-1.amazonaws.com" => "s3-us-west-1.amazonaws.com",
            "s3-eu-west-1.amazonaws.com" => "s3-eu-west-1.amazonaws.com",
            "s3.eu-central-1.amazonaws.com" => "s3.eu-central-1.amazonaws.com",
            "s3-eu-central-1.amazonaws.com" => "s3-eu-central-1.amazonaws.com",
            "s3-ap-southeast-1.amazonaws.com" => "s3-ap-southeast-1.amazonaws.com",
            "s3-ap-southeast-2.amazonaws.com" => "s3-ap-southeast-2.amazonaws.com",
            "s3-ap-northeast-1.amazonaws.com" => "s3-ap-northeast-1.amazonaws.com",
            "s3-sa-east-1.amazonaws.com" => "s3-sa-east-1.amazonaws.com"
        );
        $mform->addElement('passwordunmask', 'access_key', get_string('access_key', 'repository_s3'));
        $mform->setType('access_key', PARAM_RAW_TRIMMED);
        $mform->addElement('password', 'secret_key', get_string('secret_key', 'repository_s3'));
        $mform->setType('secret_key', PARAM_RAW_TRIMMED);
        $mform->addElement('text', 'bucket_name', get_string('bucketname', 'repository_s3bucket'));
        $mform->setType('bucket_name', PARAM_RAW_TRIMMED);
        $mform->addElement('select', 'endpoint', get_string('endpoint', 'repository_s3'), $endpointselect);
        $mform->setDefault('endpoint', 's3.amazonaws.com');
        $mform->addRule('access_key', $strrequired, 'required', null, 'client');
        $mform->addRule('secret_key', $strrequired, 'required', null, 'client');
        $mform->addRule('bucket_name', $strrequired, 'required', null, 'client');
    }

    /**
     * S3 plugins doesn't support return links of files
     *
     * @return int
     */
    public function supported_returntypes() {
        return FILE_INTERNAL;
    }

    /**
     * Is this repository accessing private data?
     *
     * @return bool
     */
    public function contains_private_data() {
        return true;
    }

    /**
     * Get S3 
     *
     * @return s3
     */
    private function create_s3() {
        $accesskey = $this->get_option('access_key');
        if (empty($accesskey)) {
            throw new moodle_exception('needaccesskey', 'repository_s3');
        }
        $secretkey = $this->get_option('secret_key');
        $endpoint = $this->get_option('endpoint');
        $s = new S3($accesskey, $secretkey, false, $endpoint);
        $s->setExceptions(true);
        return $s;
    }
}