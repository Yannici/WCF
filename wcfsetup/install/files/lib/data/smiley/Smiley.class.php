<?php
namespace wcf\data\smiley;
use wcf\data\DatabaseObject;
use wcf\system\WCF;
use wcf\util\StringUtil;

/**
 * Represents a smiley.
 * 
 * @author	Alexander Ebert
 * @copyright	2001-2015 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	data.smiley
 * @category	Community Framework
 *
 * @property-read	integer		$smileyID
 * @property-read	integer		$packageID
 * @property-read	integer|null	$categoryID
 * @property-read	string		$smileyPath
 * @property-read	string		$smileyTitle
 * @property-read	string		$smileyCode
 * @property-read	string		$aliases
 * @property-read	integer		$smileyID
 */
class Smiley extends DatabaseObject {
	/**
	 * @see	\wcf\data\DatabaseObject::$databaseTableName
	 */
	protected static $databaseTableName = 'smiley';
	
	/**
	 * @see	\wcf\data\DatabaseObject::$databaseTableIndexName
	 */
	protected static $databaseTableIndexName = 'smileyID';
	
	/**
	 * Returns the url to this smiley.
	 * 
	 * @return	string
	 */
	public function getURL() {
		return WCF::getPath().$this->smileyPath;
	}
	
	/**
	 * Returns all aliases for this smiley.
	 * 
	 * @return	array<string>
	 */
	public function getAliases() {
		if (!$this->aliases) return array();
		
		return explode("\n", StringUtil::unifyNewlines($this->aliases));
	}
}
