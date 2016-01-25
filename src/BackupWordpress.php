<?php
namespace ServiceTo;

class BackupWordpress {
	private $mysqldump = "/usr/bin/mysqldump";
	public $configdir = "/etc/httpd/conf.d/users/";
	private $tempdir = "/tmp/";
	private $tar = "/bin/tar";
	private $bzip2 = "/usr/bin/bzip2";

	private $properties = [];

	function backup($filesystem) {
		if ($dh = opendir($this->configdir)) {
			while (false !== ($entry = readdir($dh))) {
				if (strpos($entry, ".conf") !== false && is_file($this->configdir . $entry)) {
					print("Opening " . $this->configdir . $entry . "\n");
					$this->checkForBackup($this->configdir . $entry, $filesystem);
				}
			}
		}
	}

	function checkForBackup($httpdconfigfile, $filesystem) {
		$this->properties = [];
		$documentroot = "";
		if (file_exists($httpdconfigfile)) {
			$lines = file($httpdconfigfile);
			foreach ($lines as $line) {
				if (substr(trim($line), 0, strlen("ServerName")) == "ServerName") {
					$properties["servername"] = trim(substr(trim($line), strlen("ServerName") + 1), " \t\n\r\0\x0B\"");
				}
				if (substr(trim($line), 0, strlen("DocumentRoot")) == "DocumentRoot") {
					$documentroot = trim(substr(trim($line), strlen("DocumentRoot") + 1), " \t\n\r\0\x0B\"");
				}
			}
			print("Looking in " . $documentroot . "\n");
			if ($this->checkForWordpress($documentroot)) {
				$this->backupWordpress($documentroot, $filesystem);
			}
		}
	}

	function checkForWordpress($documentroot) {
		if (file_exists($documentroot . "/wp-config.php")) {
			print("Wordpress config file found in " . $documentroot . "/wp-config.php\n");
			$lines = file($documentroot . "/wp-config.php");
			foreach ($lines as $line) {
				if (substr($line, 0, strlen("define")) == "define") {
					$definition = substr(trim($line), strpos(trim($line), "(") + 1, strlen(trim($line)) - strpos(trim($line), "(") - strrpos(trim($line), ")") + 2);
					list($name, $value) = preg_split("/,/", $definition);
					$name = trim($name, " \t\n\r\0\x0B\"'");
					$value = trim($value, " \t\n\r\0\x0B\"'");
					$this->properties[$name] = $value;
				}
			}
			return true;
		}
		return false;
	}


	function backupWordpress($documentroot, $filesystem) {
		if ($this->properties["DB_NAME"] && $this->properties["DB_USER"] && $this->properties["DB_PASSWORD"] && $this->properties["DB_HOST"]) {
			$tempfile = $this->tempdir . $this->properties["DB_NAME"] . "." . date("Y-m-d-h-i-s") . ".sql";
			print("Backing up database to " . $tempfile . "\n");

			system($this->mysqldump . " -h " . $this->properties["DB_HOST"] . " -u " . $this->properties["DB_USER"] . " -p" . $this->properties["DB_PASSWORD"] . " --lock-tables=false " . $this->properties["DB_NAME"] . " > " . $tempfile);

			// compress file.
			print("Compressing...\n");
			system($this->bzip2 . " " . $tempfile);

			print("Pushing to Flysystem\n");
			$stream = fopen($tempfile . ".bz2", "r+");
			$filesystem->writeStream("backups/" . $this->properties["DB_NAME"] . "." . date("Y-m-d-h-i-s") . ".sql.bz2", $stream);

			if (is_resource($stream)) {
				fclose($stream);
			}

			// back up the wordpress content
			$tempfile = $this->tempdir . $properties["servername"] . "." . date("Y-m-d-h-i-s") . ".tar.bz2";
			print("Backing up files to " . $tempfile . "\n");

			system($this->tar . " -cjf " . $tempfile . " -C " . $documentroot . " .");

			print("Pushing to Flysystem\n");
			$stream = fopen($tempfile, "r+");
			$filesystem->writeStream("backups/" . $properties["servername"] . "." . date("Y-m-d-h-i-s") . ".tar" , $stream);

			if (is_resource($stream)) {
				fclose($stream);
			}

		}
		return false;
	}
}