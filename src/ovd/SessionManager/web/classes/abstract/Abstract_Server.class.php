<?php
/**
 * Copyright (C) 2009-2012 Ulteo SAS
 * http://www.ulteo.com
 * Author Laurent CLOUET <laurent@ulteo.com> 2010-2011
 * Author Jeremy DESVAGES <jeremy@ulteo.com> 2009-2011
 * Author Jocelyn DELALANDE <j.delalande@ulteo.com> 2012
 * Author Julien LANGLOIS <julien@ulteo.com> 2012
 * Author David PHAM-VAN <d.pham-van@ulteo.com> 2012
 * Author David LECHEVALIER <david@ulteo.com> 2012
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 **/
require_once(dirname(__FILE__).'/../../includes/core.inc.php');

/**
 * Abstraction layer between the Server instances and the SQL backend.
 */
class Abstract_Server {
	const table = 'servers';
	const table_properties = 'servers_properties';
	
	public static $server_properties = array(
		'roles'				=>	'roles',
		'roles_disabled'		=>	'roles_disabled',
		'display_name'			=>	'display_name',
		'external_name'		=>	'external_name',
		'rdp_port'		=>	'rdp_port',
		'max_sessions'		=>	'max_sessions',
		'ulteo_system'		=>	'ulteo_system',
		'windows_domain'	=>	'windows_domain',
		'disk_total'		=>	'disk_total',
		'disk_free'		=>	'disk_free'
	);

	public static function init($prefs_) {
		Logger::debug('main', 'Starting Abstract_Server::init');

		$sql_conf = $prefs_->get('general', 'sql');
		$SQL = SQL::newInstance($sql_conf);

		$servers_table_structure = array(
			'id'			=>	'varchar(255)',
			'fqdn'			=>	'varchar(255)',
			'status'		=>	'varchar(255)',
			'registered'	=>	'int(8)',
			'locked'		=>	'int(8)',
			'type'			=>	'varchar(255)',
			'version'		=>	'varchar(255)',
			'cpu_model'		=>	'varchar(255)',
			'cpu_nb_cores'	=>	'int(8)',
			'cpu_load'		=>	'int(8)',
			'ram_total'		=>	'int(16)',
			'ram_used'		=>	'int(16)',
			'timestamp'		=>	'int(10)'
		);

		$ret = $SQL->buildTable(self::table, $servers_table_structure, array('id'), array('U_FQDN'=>array('fqdn')));

		if (! $ret) {
			Logger::error('main', 'Unable to create MySQL table \''.self::table.'\'');
			return false;
		}

		Logger::debug('main', 'MySQL table \''.self::table.'\' created');

		$servers_properties_table_structure = array(
			'server'		=>	'varchar(255)',
			'property'		=>	'varchar(64)',
			'value'			=>	'varchar(255)'
		);

		$ret = $SQL->buildTable(self::table_properties, $servers_properties_table_structure, array('server', 'property'));

		if (! $ret) {
			Logger::error('main', 'Unable to create MySQL table \''.self::table_properties.'\'');
			return false;
		}

		Logger::debug('main', 'MySQL table \''.self::table_properties.'\' created');

		return true;
	}

	public static function exists($id_) {
		Logger::debug('main', 'Starting Abstract_Server::exists for \''.$id_.'\'');

		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT 1 FROM #1 WHERE @2 = %3 LIMIT 1', self::table, 'id', $id_);
		$total = $SQL->NumRows();

		if ($total == 0)
			return false;

		return true;
	}

	public static function load($id_) {
		Logger::debug('main', 'Starting Abstract_Server::load for \''.$id_.'\'');

		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT * FROM #1 WHERE @2 = %3 LIMIT 1', self::table, 'id', $id_);
		$total = $SQL->NumRows();

		if ($total == 0) {
			Logger::error('main', "Abstract_Server::load($id_) failed: NumRows == 0");
			return false;
		}

		$row = $SQL->FetchResult();

		$buf = self::generateFromRow($row);

		return $buf;
	}

	public static function save($server_) {
		Logger::debug('main', 'Starting Abstract_Server::save for \''.$server_->fqdn.'\'');

		$SQL = SQL::getInstance();

		if (! Abstract_Server::exists($server_->id)) {
			Logger::debug('main', "Abstract_Server::save($server_) server does NOT exist, we must create it");

			if (! Abstract_Server::create($server_)) {
				Logger::error('main', "Abstract_Server::save($server_) create failed");
				return false;
			}
		}

		$SQL->DoQuery('UPDATE #1 SET @2=%3,@4=%5,@6=%7,@8=%9,@10=%11,@12=%13,@14=%15,@16=%17,@18=%19,@20=%21,@22=%23,@24=%25 WHERE @26 = %27 LIMIT 1', self::table, 'fqdn', $server_->fqdn, 'status', $server_->status, 'registered', (int)$server_->registered, 'locked', (int)$server_->locked, 'type', $server_->type, 'version', $server_->version, 'cpu_model', $server_->cpu_model,
		'cpu_nb_cores', $server_->cpu_nb_cores, 'cpu_load', (int)($server_->cpu_load*100), 'ram_total', $server_->ram_total, 'ram_used', $server_->ram_used, 'timestamp', time(), 'id', $server_->id);

		$properties = Abstract_Server::loadProperties($server_);

		foreach (Abstract_Server::$server_properties as $object_property => $db_property)
			Abstract_Server::saveProperty($server_, $object_property, $db_property, (isset($properties[$object_property])?$properties[$object_property]:NULL));

		return true;
	}

	private static function loadProperties($server_) {
		Logger::debug('main', 'Starting Abstract_Server::loadProperties for \''.$server_->fqdn.'\'');

		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT @1,@2 FROM #3 WHERE @4 = %5', 'property', 'value', self::table_properties, 'server', $server_->id);
		$rows = $SQL->FetchAllResults();

		$properties = array();
		foreach ($rows as $row)
			$properties[$row['property']] = json_unserialize($row['value']);

		return $properties;
	}

	private static function saveProperty($server_, $object_property_, $db_property_, $old_property_) {
		$property_ = ((isset($server_->$object_property_))?json_serialize($server_->$object_property_):NULL);
		$old_property_ = ((! is_null($old_property_))?json_serialize($old_property_):NULL);

		$SQL = SQL::getInstance();

		if (! is_null($old_property_) && is_null($property_))
			$SQL->DoQuery('DELETE FROM #1 WHERE @2 = %3 AND @4 = %5 LIMIT 1', self::table_properties, 'property', $db_property_, 'server', $server_->id);
		elseif (is_null($old_property_) && ! is_null($property_))
			$SQL->DoQuery('INSERT INTO #1 (@2,@3,@4) VALUES(%5,%6,%7)', self::table_properties, 'server', 'property', 'value', $server_->id, $db_property_, $property_);
		elseif ($old_property_ != $property_)
			$SQL->DoQuery('UPDATE #1 SET @2=%3 WHERE @4 = %5 AND @6 = %7 LIMIT 1', self::table_properties, 'value', $property_, 'property', $db_property_, 'server', $server_->id);

		return true;
	}

	public static function create($server_) {
		Logger::debug('main', 'Starting Abstract_Server::create for \''.$server_->fqdn.'\'');

		if (Abstract_Server::exists($server_->id)) {
			Logger::error('main', 'Abstract_Server::create(\''.$server_->id.'\') server already exists');
			return false;
		}

		$SQL = SQL::getInstance();
		$SQL->DoQuery('INSERT INTO #1 (@2) VALUES (%3)', self::table, 'id', $server_->id);

		return ($SQL->AffectedRows() == 1);
	}

	public static function modify($server_) {
		Logger::debug('main', 'Starting Abstract_Server::modify for \''.$server_->fqdn.'\'');

		$SQL = SQL::getInstance();

		if (! Abstract_Server::load($server_->id)) {
			Logger::error('main', 'Abstract_Server::modify('.$server_->fqdn.') failed to load server');
			return false;
		}

		$SQL->DoQuery('UPDATE #1 SET @2=%3,@4=%5,@6=%7,@8=%9,@10=%11,@12=%13,@14=%15,@16=%17,@18=%19,@20=%21,@22=%23,@24=%25 WHERE @26 = %27 LIMIT 1', self::table, 'fqdn', $server_->fqdn, 'status', $server_->status, 'registered', (int)$server_->registered, 'locked', (int)$server_->locked, 'type', $server_->type, 'version', $server_->version, 'cpu_model', $server_->cpu_model,
		'cpu_nb_cores', $server_->cpu_nb_cores, 'cpu_load', (int)($server_->cpu_load*100), 'ram_total', $server_->ram_total, 'ram_used', $server_->ram_used, 'timestamp', time(), 'id', $server_->id);

		$properties = Abstract_Server::loadProperties($server_);

		foreach (Abstract_Server::$server_properties as $object_property => $db_property)
			Abstract_Server::saveProperty($server_, $object_property, $db_property, (isset($properties[$object_property])?$properties[$object_property]:NULL));

		return true;
	}

	public static function delete($id_) {
		Logger::debug('main', 'Starting Abstract_Server::delete for \''.$id_.'\'');

		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT 1 FROM #1 WHERE @2 = %3 LIMIT 1', self::table, 'id', $id_);
		$total = $SQL->NumRows();

		if ($total == 0) {
			Logger::error('main', "Abstract_Server::delete($id_) server does not exist (NumRows == 0)");
			return false;
		}

		$sessions_liaisons = Abstract_Liaison::load('ServerSession', $id_, NULL);
		foreach ($sessions_liaisons as $sessions_liaison) {
			$session = Abstract_Session::load($sessions_liaison->group);
			if (! $session)
				continue;

			$session->orderDeletion(true, Session::SESSION_END_STATUS_SERVER_DELETED);
		}
		Abstract_Liaison::delete('ServerSession', $id_, NULL);
		
		$a_server = Abstract_Server::load($id_);
		$roles = $a_server->getAttribute('roles');
		if (is_array($roles)) {
			foreach ($roles as $a_role => $role_enabled) {
				Abstract_Server::removeRole($id_, $a_role);
			}
		}

		$SQL->DoQuery('DELETE FROM #1 WHERE @2 = %3', self::table_properties, 'server', $id_);
		$SQL->DoQuery('DELETE FROM #1 WHERE @2 = %3 LIMIT 1', self::table, 'id', $id_);

		return true;
	}

	public static function removeRole($id_, $role_) {
		Logger::debug('main', "Starting Abstract_Server::removeRole for '$id_' removing '$role_'");
		
		$a_server = Abstract_Server::load($id_);
		if (is_object($a_server) == false) {
			Logger::error('main', "Starting Abstract_Server::removeRole error failed to load server '$id_'");
			return false;
		}
		
		$roles = $a_server->getAttribute('roles');
		if (is_array($roles) == false) {
			return false;
		}
		
		if (in_array($role_, $roles) == false) {
			return false;
		}
		
		switch ($role_) {
			case Server::SERVER_ROLE_APS:
				$prefs = Preferences::getInstance();
				if (! $prefs)
					die_error('get Preferences failed',__FILE__,__LINE__);
				
				$slave_server_settings = $prefs->get('general', 'slave_server_settings');
				$remove_orphan = (bool)$slave_server_settings['remove_orphan'];
				
				Abstract_Liaison::delete('ApplicationServer', NULL, $a_server->id);
				if ($remove_orphan) {
					$apps = $a_server->getApplications();
					$applicationDB = ApplicationDB::getInstance();
					
					// remove the orphan applications
					if (is_array($apps)) {
						foreach ($apps as $an_application) {
							if ($an_application->isOrphan()) {
								Logger::debug('main', "Abstract_Server::delete $an_application is orphan");
								$applicationDB->remove($an_application);
							}
						}
					}
				}
				
				$tm = new Tasks_Manager();
				$tm->load_from_server($id_);
				foreach ($tm->tasks as $a_task) {
					$tm->remove($a_task->id);
				}
			break;
			
			case Server::SERVER_ROLE_FS:
				if (Preferences::moduleIsEnabled('ProfileDB')) {
					$profiledb = ProfileDB::getInstance();
					$folders = $profiledb->importFromServer($id_);
					foreach ($folders as $a_folder) {
						$profiledb->remove($a_folder->id);
						if ($profiledb->isInternal())
							$a_server->deleteNetworkFolder($a_folder->id, true);
					}
				}
				
				if (Preferences::moduleIsEnabled('SharedFolderDB')) {
					$sharedfolderdb = SharedFolderDB::getInstance();
					$folders = $sharedfolderdb->importFromServer($id_);
					foreach ($folders as $a_folder) {
						$sharedfolderdb->remove($a_folder->id);
						if ($sharedfolderdb->isInternal())
							$a_server->deleteNetworkFolder($a_folder->id, true);
					}
				}
				
			break;
			
// 			case Server::SERVER_ROLE_GATEWAY:
// 			break;
		}
		return true;
	}

	private static function generateFromRow($row_) {
		foreach ($row_ as $k => $v)
			$$k = $v;

		$buf = new Server((string)$id);
		$buf->fqdn = (string)$fqdn;
		$buf->status = (string)$status;
		$buf->registered = (bool)$registered;
		$buf->locked = (bool)$locked;
		$buf->type = (string)$type;
		$buf->version = (string)$version;
		$buf->cpu_model = (string)$cpu_model;
		$buf->cpu_nb_cores = (int)$cpu_nb_cores;
		$buf->cpu_load = (float)($cpu_load/100);
		$buf->ram_total = (int)$ram_total;
		$buf->ram_used = (int)$ram_used;

		$properties = Abstract_Server::loadProperties($buf);

		foreach (Abstract_Server::$server_properties as $object_property => $db_property) {
			if (isset($properties[$db_property]))
				$buf->$object_property = $properties[$db_property];
		}

		return $buf;
	}

	public static function load_all() {
		Logger::debug('main', 'Starting Abstract_Server::load_all');

		$prefs = Preferences::getInstance();
		if (! $prefs) {
			Logger::critical('main', 'get Preferences failed in '.__FILE__.' line '.__LINE__);
			return array();
		}

		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT * FROM #1', self::table);
		$rows = $SQL->FetchAllResults();

		$servers = array();
		foreach ($rows as $row) {
			$server = self::generateFromRow($row);
			if (! is_object($server))
				continue;

			$servers[] = $server;
		}

		return $servers;
	}

	public static function load_available($with_locked_=false) {
		$servers = Abstract_Server::load_all();

		foreach ($servers as $k => $server) {
			if (! $server->getAttribute('registered'))
				unset($servers[$k]);

			if ($with_locked_ === false) {
				if ($server->getAttribute('locked'))
					unset($servers[$k]);
			}

			if (! $server->isOnline())
				unset($servers[$k]);
		}

		return $servers;
	}

	public static function load_by_status($status_) {
		$servers = Abstract_Server::load_all();

		foreach ($servers as $k => $server) {
			switch ($status_) {
				case Server::SERVER_STATUS_ONLINE:
					if (! $server->getAttribute('registered'))
						unset($servers[$k]);

					if (! $server->isOnline())
						unset($servers[$k]);
					break;
				case Server::SERVER_STATUS_OFFLINE:
				case Server::SERVER_STATUS_BROKEN:
				default:
					if ($server->getAttribute('status') != $status_)
						unset($servers[$k]);
					break;
			}
		}

		return $servers;
	}

	public static function load_registered($registered_=true) {
		$servers = Abstract_Server::load_all();

		foreach ($servers as $k => $server) {
			if (! $server->getAttribute('registered') && $registered_ === true)
				unset($servers[$k]);
			elseif ($server->getAttribute('registered') && $registered_ === false)
				unset($servers[$k]);
		}

		return $servers;
	}

	public static function load_by_fqdn($fqdn_) {
		Logger::debug('main', 'Starting Abstract_Server::load_by_fqdn for \''.$fqdn_.'\'');
		
		$SQL = SQL::getInstance();
		
		$SQL->DoQuery('SELECT * FROM #1 WHERE @2 = %3 LIMIT 1', self::table, 'fqdn', $fqdn_);
		$total = $SQL->NumRows();
		
		if ($total == 0) {
			Logger::error('main', "Abstract_Server::load($fqdn_) failed: NumRows == 0");
			return null;
		}
		
		$row = $SQL->FetchResult();
		
		$buf = self::generateFromRow($row);
		
		return $buf;
	}
	
	public static function load_available_by_type($type_, $with_locked_=false) {
		$servers = Abstract_Server::load_available($with_locked_);

		foreach ($servers as $k => $server) {
			if ($server->getAttribute('type') != $type_)
				unset($servers[$k]);
		}

		return $servers;
	}

	public static function load_available_by_role($role_, $with_locked_=false) {
		$servers = Abstract_Server::load_available($with_locked_);

		foreach ($servers as $k => $server) {
			if (! array_key_exists($role_, $server->getRoles()))
				unset($servers[$k]);
		}

		return $servers;
	}

	public static function load_available_by_role_sorted_by_load_balancing($role_) {
		$available_servers = Abstract_Server::load_available_by_role($role_);
		
		return Server::fire_load_balancing($available_servers, $role_);
	}

	public static function uptodate($server_) {
		Logger::debug('main', 'Starting Abstract_Server::uptodate for \''.$server_->fqdn.'\'');
		
		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT @1 FROM #2 WHERE @3 = %4 LIMIT 1', 'timestamp', self::table, 'id', $server_->id);
		$total = $SQL->NumRows();

		if ($total == 0) {
			Logger::warning('main', "Abstract_Server::uptodate($server_) server does not exist (NumRows == 0)");
			return false;
		}

		$row = $SQL->FetchResult();

		if ((int)$row['timestamp'] < time()-DEFAULT_CACHE_DURATION)
			return false;

		return true;
	}
}
