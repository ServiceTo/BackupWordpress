<?php
namespace ServiceTo;

class BackupWordpress {
	private $mysqldump = "/usr/bin/mysqldump";
	public $configdir = "/etc/httpd/conf.d/users/";
	private $tempdir = "/tmp/";
	private $tar = "/bin/tar"
	private $bzip2 = "/usr/bin/bzip2";

	function backup() {
		if ($dh = opendir($this->configdir)) {
			while (false !== ($entry = readdir($dh))) {
				if (strpos($entry, ".conf") !== false && is_file($this->configdir . $entry)) {
					$this->checkForBackup($this->configdir . $entry);
				}
			}
		}
	}

	function checkForBackup($httpdconfigfile) {
		if (file_exists($httpdconfigfile)) {
			$lines = file($httpdconfigfile);
			foreach ($lines as $line) {
				if (substr(trim($line), 0, strlen("DocumentRoot") == "DocumentRoot") {
					$documentroot = trim(substr(trim($line, strlen("DocumentRoot") + 1)), " \t\n\r\0\x0B\"");
					if ($this->checkForWordpress($documentroot)) {
						$this->backupWordpress($documentroot);
					}
				}
			}
		}
	}

	function checkForWordpress($documentroot) {
		if (file_exists($documentroot . "/wp_config.php")) {
			$lines = file($documentroot . "/wp_config.php");
			foreach ($lines as $line) {
				if (substr($line, 0, strlen("define")) == "define") {
					eval(trim($line));
				}
			}
		}
		return false;
	}

	function backupWordpress($documentroot) {
		if (DB_NAME && DB_USER && DB_PASSWORD && DB_HOST) {
			$tempfile = $this->tempdir . DB_NAME . "." . date("Y-m-d-h-i-s") . ".sql";

			system($this->mysqldump . " -h " . DB_HOST . " -u " . DB_USER . " -p" . DB_PASSWORD . " " . DB_NAME . " > " . $tempfile);

			// compress file.
			system($this->bzip2 . " " . $tempfile);

			$stream = fopen($tempfile, "r+");

		}
		return false;
	}
