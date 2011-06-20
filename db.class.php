<?php

/* 
	DB class by Kasheftin

		v. 0.10 (2010.11.23)
			- Tracking start.
			- Only mysql databases supported.
*/	

class DB
{
	static protected $oInstance = null;
	protected $dbs = array();
	protected $current_connection_id = null;
	protected $CONFIG = array();

	static public function getInstance()
	{
		if (isset(self::$oInstance) && (self::$oInstance instanceof self)) {
			return self::$oInstance;
		}
		else {
			self::$oInstance = new self();
			return self::$oInstance;
		}
	}
	public function __clone() { }
	protected function __construct() { }

	static public function setConfig($CONFIG)
	{
		$o = self::getInstance();
		$o->CONFIG = $CONFIG;
	}

	protected function setupConnection($connection_id=null)
	{
		if (isset($connection_id) && isset($this->CONFIG["connections"][$connection_id])) {
			if (isset($this->dbs[$connection_id]) && ($this->dbs[$connection_id] instanceof PDO)) {
				return $this->dbs[$connection_id];
			}
			else {
				$this->dbs[$connection_id] = new PDO($this->CONFIG["connections"][$connection_id]["sys"],$this->CONFIG["connections"][$connection_id]["user"],$this->CONFIG["connections"][$connection_id]["pass"]);
				if ($this->CONFIG["connections"][$connection_id]["encoding"]) {
					$this->dbs[$connection_id]->exec("set character_set_client='" . $this->CONFIG["connections"][$connection_id]["encoding"] . "'");
					$this->dbs[$connection_id]->exec("set character_set_results='" . $this->CONFIG["connections"][$connection_id]["encoding"] . "'");
					$this->dbs[$connection_id]->exec("set collation_connection='" . $this->CONFIG["connections"][$connection_id]["encoding"] . "_bin'");
				}
				switch ($this->CONFIG["connections"][$connection_id][errmode]) {
					case "warning":
						$this->dbs[$connection_id]->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_WARNING);
						break;
					case "exception":
						$this->dbs[$connection_id]->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
						break;
					default:
						$this->dbs[$connection_id]->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_SILENT);
				}

				if (class_exists("DEBUG")) DEBUG::log($this->CONFIG["connections"][$connection_id]["sys"] . " connection established, connection_id=" . $connection_id,"SQL");

				return $this->dbs[$connection_id];
			}
		}
		else throw new Exception(__CLASS__ . "::" . __METHOD__ . ": connection $connection_id settings not found in CONFIG");
	}

	protected function setConnection($connection_id=null)
	{
		if ($db = $this->setupConnection($connection_id)) {
			$this->current_connection_id = $connection_id;
			return $db;
		}
		else throw new Exception(__CLASS__ . "::" . __METHOD__ . ": connection $connection_id is not set");
		return null;
	}

	static public function set($connection_id=null)
	{
		$o = self::getInstance();
		return $o->setConnection($connection_id);
	}

	protected function query($query,$opts=null,$connection_id=null)
	{
		if (isset($connection_id))
			$db = $this->setupConnection($connection_id);
		elseif ($this->current_connection_id == null) 
			$db = $this->setConnection($this->CONFIG["default_connection_id"]);
		else
			$db = $this->dbs[$this->current_connection_id];

		if (!isset($db) || !($db instanceof PDO)) throw new Exception(__CLASS__ . "::" . __METHOD__ . ": error while retreiving connection, can't get connection");

		$query_id = md5($query . ($opts?join(",",$opts):""));

		if (class_exists("DEBUG")) DEBUG::logStart($query_id);

		$is_insert = 0;
		if (preg_match("/^insert/i",trim($query)))
			$is_insert = 1;

		try
		{
			if ($is_insert)
				$db->beginTransaction();

			if (isset($opts)) {
				$res = $db->prepare($query);
				$res->execute($opts);
			}
			else {
				$res = $db->query($query);
			}

			if ($is_insert)
			{
				$insert_id = $db->lastInsertId();
				$db->commit();
			}

			if (class_exists("DEBUG")) DEBUG::logEnd($query_id,$query,$opts,"SQL");

			if ($is_insert && $insert_id) return $insert_id;

			return $res;
		}
		catch (PDOException $e)
		{
			if ($is_insert)
				$db->rollback();

			if (class_exists("DEBUG"))
			{
				DEBUG::logEnd($query_id,$e->getMessage(),$query,$opts,"ERROR&SQL");
				return null;
			}

			$error = "PDOException: " . $e->getMessage() . "\nOccurs in query: " . $query . (isset($opts)?" with opts: " . (join(",",$opts)):" without opts");
			if ($this->CONFIG["errformat"] == "html")
				$out = "<div style='margin: 10px; border: 1px solid #dedede; padding: 5px; font-size: 0.85em;'>" . str_replace("\n","<br />",$error) . "</div>";
			else
				$out = "<!-- $error -->\n";

			if ($this->CONFIG["errformat"] != "none")
				echo $out;
			
		}
		return null;
	}

	static public function q($query,$opts=null,$connection_id=null)
	{
		$o = self::getInstance();
		return $o->query($query,$opts,$connection_id);
	}

	static public function f($query,$opts=null,$connection_id=null)
	{
		$query_id = md5($query);
		$o = self::getInstance();
		$res = $o->query($query,$opts,$connection_id);
		if (isset($res) && ($res instanceof PDOStatement))
		{
			$rws = $res->fetchAll();
			return $rws;
		}
		return null;
	}

	static public function f1($query,$opts=null,$connection_id=null)
	{
		$o = self::getInstance();
		if (!preg_match("/limit\s*\d+\s*,\s*\d+/",$query))
			$query .= " limit 0,1";
		$res = $o->query($query,$opts,$connection_id);
		if (isset($res) && ($res instanceof PDOStatement)) {
			$rw = $res->fetch();
			return $rw;
		}
		return null;
	}
}
