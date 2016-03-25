<?php
namespace wcf\data\acp\session;
use wcf\data\DatabaseObject;

/**
 * Represents an ACP session.
 * 
 * @author	Alexander Ebert
 * @copyright	2001-2015 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	data.acp.session
 * @category	Community Framework
 *
 * @property-read	string		$sessionID
 * @property-read	integer|null	$userID
 * @property-read	string		$ipAddress
 * @property-read	string		$userAgent
 * @property-read	integer		$lastActivityTime
 * @property-read	string		$requestURI
 * @property-read	string		$requestMethod
 * @property-read	string		$controller
 * @property-read	string		$parentObjectType
 * @property-read	integer		$parentObjectID
 * @property-read	string		$objectType
 * @property-read	integer		$objectID
 */
class ACPSession extends DatabaseObject {
	/**
	 * @see	\wcf\data\DatabaseObject::$databaseTableName
	 */
	protected static $databaseTableName = 'acp_session';
	
	/**
	 * @see	\wcf\data\DatabaseObject::$databaseTableIndexIsIdentity
	 */
	protected static $databaseTableIndexIsIdentity = false;
	
	/**
	 * @see	\wcf\data\DatabaseObject::$databaseTableIndexName
	 */
	protected static $databaseTableIndexName = 'sessionID';
	
	/**
	 * Returns true if this session type supports persistent logins.
	 * 
	 * @return	boolean
	 */
	public static function supportsPersistentLogins() {
		return false;
	}
	
	/**
	 * Returns true if this session type supports virtual sessions (sharing the same
	 * session among multiple clients).
	 * 
	 * @return	boolean
	 */
	public static function supportsVirtualSessions() {
		return false;
	}
}
