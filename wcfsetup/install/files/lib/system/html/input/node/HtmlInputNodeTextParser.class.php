<?php
namespace wcf\system\html\input\node;
use wcf\data\bbcode\media\provider\BBCodeMediaProvider;
use wcf\data\smiley\Smiley;
use wcf\data\smiley\SmileyCache;
use wcf\system\bbcode\BBCodeHandler;
use wcf\system\bbcode\HtmlBBCodeParser;
use wcf\system\database\util\PreparedStatementConditionBuilder;
use wcf\system\WCF;
use wcf\util\JSON;
use wcf\util\StringUtil;

/**
 * Parses all text nodes searching for links, media, mentions or smilies.
 * 
 * @author	Alexander Ebert
 * @copyright	2001-2017 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	WoltLabSuite\Core\System\Html\Input\Node
 * @since	3.0
 */
class HtmlInputNodeTextParser {
	/**
	 * list of markers per element that will face a replacement
	 * @var \DOMElement[][]
	 */
	protected $elementStack = [];
	
	/**
	 * @var HtmlInputNodeProcessor
	 */
	protected $htmlInputNodeProcessor;
	
	/**
	 * list of text nodes that will face a replacement
	 * @var \DOMText[]
	 */
	protected $nodeStack = [];
	
	/**
	 * number of found smilies
	 * @var integer
	 */
	protected $smileyCount = 0;
	
	/**
	 * list of smilies by smiley code
	 * @var Smiley[]
	 */
	protected $smilies = [];
	
	/**
	 * @var string[]
	 */
	protected $sourceBBCodes = [];
	
	/**
	 * forbidden characters
	 * @var	string
	 */
	protected static $illegalChars = '[^\x0-\x2C\x2E\x2F\x3A-\x40\x5B-\x60\x7B-\x7F]+';
	
	/**
	 * regex for user mentions
	 * @var string
	 */
	protected static $userRegex = "~
		\\B                                             # any non-word character, whitespace, string start is fine
		@
		(
			([^',\s][^,\s]{2,})(?:\s[^@,\s][^,\s]*)?	# either at most two strings,
									# not containing the whitespace or the comma,
									# not starting with a single quote
									# the second string not starting with the at sign
									# separated by a single whitespace character
		|
			'(?:''|[^']){3,}'				# or a string delimited by single quotes
		)
	~x";
	
	/**
	 * HtmlInputNodeTextParser constructor.
	 * 
	 * @param HtmlInputNodeProcessor $htmlInputNodeProcessor
	 * @param integer $smileyCount
	 */
	public function __construct(HtmlInputNodeProcessor $htmlInputNodeProcessor, $smileyCount = 0) {
		$this->htmlInputNodeProcessor = $htmlInputNodeProcessor;
		$this->sourceBBCodes = HtmlBBCodeParser::getInstance()->getSourceBBCodes();
		
		if (MODULE_SMILEY) {
			$this->smileyCount = $smileyCount;
			
			// get smilies
			$smilies = SmileyCache::getInstance()->getSmilies();
			$categories = SmileyCache::getInstance()->getCategories();
			
			foreach ($smilies as $categoryID => $categorySmilies) {
				if ($categories[$categoryID ?: null]->isDisabled) continue;
				
				/** @var Smiley $smiley */
				foreach ($categorySmilies as $smiley) {
					foreach ($smiley->smileyCodes as $smileyCode) {
						$this->smilies[$smileyCode] = $smiley;
					}
				}
			}
			
			uksort($this->smilies, function($a, $b) {
				$lengthA = mb_strlen($a);
				$lengthB = mb_strlen($b);
				
				if ($lengthA < $lengthB) {
					return 1;
				}
				else if ($lengthA === $lengthB) {
					return 0;
				}
				
				return -1;
			});
		}
	}
	
	/**
	 * Parses all text nodes searching for possible replacements.
	 */
	public function parse() {
		// get all text nodes
		$nodes = [];
		/** @var \DOMText $node */
		foreach ($this->htmlInputNodeProcessor->getXPath()->query('//text()') as $node) {
			$value = StringUtil::trim($node->textContent);
			if (empty($value)) {
				// skip empty nodes
				continue;
			}
			
			// check if node is within a code element or link
			if ($this->hasCodeParent($node) || $this->hasLinkParent($node)) {
				continue;
			}
			
			$nodes[] = $node;
		}
		
		// search for mentions, this step is separated to reduce the
		// impact of querying the database for many matches
		$usernames = [];
		for ($i = 0, $length = count($nodes); $i < $length; $i++) {
			/** @var \DOMText $node */
			$node = $nodes[$i];
			
			$oldValue = $value = $node->textContent;
			
			// this extra step ensures that we don't trip over some
			// random &nbsp; inserted by the editor and at the same
			// time gets rid of them afterwards
			$value = preg_replace('~\x{00A0}~u', ' ', $value);
			
			// zero-width whitespace causes a lot of issues and is not required 
			$value = preg_replace('~\x{200B}~u', '', $value);
			
			if ($value !== $oldValue) {
				$node->nodeValue = $value;
			}
			
			$this->detectMention($node, $value, $usernames);
		}
		
		$users = [];
		if (!empty($usernames)) {
			$users = $this->lookupUsernames($usernames);
		}
		
		$allowEmail = BBCodeHandler::getInstance()->isAvailableBBCode('email');
		$allowMedia = BBCodeHandler::getInstance()->isAvailableBBCode('media');
		$allowURL = BBCodeHandler::getInstance()->isAvailableBBCode('url');
		
		for ($i = 0, $length = count($nodes); $i < $length; $i++) {
			/** @var \DOMText $node */
			$node = $nodes[$i];
			$oldValue = $value = $node->textContent;
			
			if (!empty($users)) {
				$value = $this->parseMention($node, $value, $users);
			}
			
			if ($allowURL || $allowMedia) {
				$value = $this->parseURL($node, $value, $allowURL, $allowMedia);
			}
			
			if ($allowEmail) {
				$value = $this->parseEmail($node, $value);
			}
			
			if ($this->smileyCount !== 50) {
				$value = $this->parseSmiley($node, $value);
			}
			
			if ($value !== $oldValue) {
				$node->nodeValue = $value;
			}
		}
		
		// replace matches
		for ($i = 0, $length = count($this->nodeStack); $i < $length; $i++) {
			$this->replaceMatches($this->nodeStack[$i], $this->elementStack[$i]);
		}
	}
	
	/**
	 * Detects mentions in text nodes.
	 * 
	 * @param       \DOMText        $text           text node
	 * @param       string          $value          node value
	 * @param       string[]        $usernames      list of already found usernames
	 */
	protected function detectMention(\DOMText $text, $value, array &$usernames) {
		if (mb_strpos($value, '@') === false) {
			return;
		}
		
		if (preg_match_all(self::$userRegex, $value, $matches, PREG_PATTERN_ORDER)) {
			// $i = 1 to skip the full match
			for ($i = 1, $length = count($matches); $i < $length; $i++) {
				for ($j = 0, $innerLength = count($matches[$i]); $j < $innerLength; $j++) {
					if ($matches[$i][$j] === '') {
						continue;
					}
					
					$username = $this->getUsername($matches[$i][$j]);
					if (!isset($usernames[$username])) {
						$usernames[$username] = $username;
					}
				}
			}
		}
	}
	
	/**
	 * Matches the found usernames against the user table.
	 * 
	 * @param       string[]        $usernames      list of found usernames
	 * @return      string[]        list of valid usernames
	 */
	protected function lookupUsernames(array $usernames) {
		$exactValues = [];
		$likeValues = [];
		foreach ($usernames as $username) {
			if (mb_strpos($username, ' ') !== false) {
				// string contains a whitespace, account for names that
				// are built up with more than two words
				$likeValues[] = $username;
			}
			else {
				$exactValues[] = $username;
			}
		}
		
		$conditions = new PreparedStatementConditionBuilder(true, 'OR');
		
		if (!empty($exactValues)) {
			$conditions->add('username IN (?)', [$exactValues]);
		}
		
		if (!empty($likeValues)) {
			for ($i = 0, $length = count($likeValues); $i < $length; $i++) {
				$conditions->add('username LIKE ?', [str_replace('%', '', $likeValues[$i]) . '%']);
			}
		}
		
		$sql = "SELECT  userID, username
			FROM    wcf".WCF_N."_user
			".$conditions;
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute($conditions->getParameters());
		$users = $statement->fetchMap('userID', 'username');
		
		// sort usernames with the longest one being first
		uasort($users, function($a, $b) {
			$lengthA = mb_strlen($a);
			$lengthB = mb_strlen($b);
			
			if ($lengthA < $lengthB) {
				return 1;
			}
			else if ($lengthA === $lengthB) {
				return 0;
			}
			
			return -1;
		});
		
		return $users;
	}
	
	/**
	 * Parses text nodes and searches for mentions.
	 * 
	 * @param       \DOMText        $text           text node
	 * @param       string          $value          node value
	 * @param       string[]        $users          list of usernames by user id
	 * @return      string          modified node value with replacement placeholders
	 */
	protected function parseMention(\DOMText $text, $value, array $users) {
		if (mb_strpos($value, '@') === false) {
			return $value;
		}
		
		foreach ($users as $userID => $username) {
			$offset = 0;
			do {
				$needle = '@' . $username;
				$pos = mb_stripos($value, $needle, $offset);
				
				// username not found, maybe it is quoted
				if ($pos === false) {
					$needle = "@'" . str_ireplace("'", "''", $username) . "'";
					$pos = mb_stripos($value, $needle);
				}
				
				if ($pos !== false) {
					$element = $text->ownerDocument->createElement('woltlab-metacode');
					$element->setAttribute('data-name', 'user');
					$element->setAttribute('data-attributes', base64_encode(JSON::encode([$userID])));
					$element->appendChild($text->ownerDocument->createTextNode($username));
					
					$marker = $this->addReplacement($text, $element);
					
					// we use preg_replace() because the username could appear multiple times
					// and we need to replace them one by one, also avoiding only replacing
					// the non-quoted username even though both variants are present
					$value = preg_replace('~' . preg_quote($needle, '~') . '~i', $marker, $value, 1);
					
					$offset = $pos + 1;
				}
			}
			while ($pos);
		}
		
		return $value;
	}
	
	/**
	 * Parses regular links and media links contained in text nodes.
	 * 
	 * @param       \DOMText        $text           text node
	 * @param       string          $value          node value
	 * @param       boolean         $allowURL       url bbcode is allowed
	 * @param       boolean         $allowMedia     media bbcode is allowed
	 * @return      string          modified node value with replacement placeholders
	 */
	protected function parseURL(\DOMText $text, $value, $allowURL, $allowMedia) {
		static $urlPattern = '';
		if ($urlPattern === '') {
			$urlPattern = '~
			(?<!\B|"|\'|=|/|,|\?|\.)
			(?:						# hostname
				(?:ftp|https?)://'.static::$illegalChars.'(?:\.'.static::$illegalChars.')*
				|
				www\.(?:'.static::$illegalChars.'\.)+
				(?:[a-z]{2,63}(?=\b))			# tld
			)
			
			(?::\d+)?					# port
			
			(?:
				/
				[^!.,?;"\'<>()\[\]{}\s]*
				(?:
					[!.,?;(){}]+ [^!.,?;"\'<>()\[\]{}\s]+
				)*
			)?~ix';
		}
		
		return preg_replace_callback($urlPattern, function($matches) use ($text, $allowURL, $allowMedia) {
			$link = $matches[0];
			
			if (BBCodeMediaProvider::isMediaURL($link)) {
				if ($allowMedia) {
					$element = $this->htmlInputNodeProcessor->createMetacodeElement($text, 'media', []);
					$element->appendChild($element->ownerDocument->createTextNode($link));
				}
				else {
					return $matches[0];
				}
			}
			else {
				if ($allowURL) {
					// add protocol if necessary
					if (!preg_match('/[a-z]:\/\//si', $link)) $link = 'http://'.$link;
					
					$element = $text->ownerDocument->createElement('a');
					$element->setAttribute('href', $link);
					$element->appendChild($element->ownerDocument->createTextNode($link));
				}
				else {
					return $matches[0];
				}
			}
			
			return $this->addReplacement($text, $element);
		}, $value);
	}
	
	/**
	 * Parses text nodes and replaces email addresses.
	 * 
	 * @param       \DOMText        $text           text node
	 * @param       string          $value          node value
	 * @return      string          modified node value with replacement placeholders
	 */
	protected function parseEmail(\DOMText $text, $value) {
		if (mb_strpos($value, '@') === false) {
			return $value;
		}
		
		static $emailPattern = null;
		if ($emailPattern === null) {
			$emailPattern = '~
			(?<!\B|"|\'|=|/|,|:)
			(?:)
			\w+(?:[\.\-]\w+)*
			@
			(?:'.self::$illegalChars.'\.)+		# hostname
			(?:[a-z]{2,4}(?=\b))
			(?!"|\'|\-|\]|\.[a-z])~ix';
		}
		
		return preg_replace_callback($emailPattern, function($matches) use ($text) {
			$email = $matches[0];
			
			$element = $this->htmlInputNodeProcessor->createMetacodeElement($text, 'email', [$email]);
			
			return $this->addReplacement($text, $element);
		}, $value);
	}
	
	/**
	 * Parses text nodes and replaces smilies.
	 * 
	 * @param       \DOMText        $text           text node
	 * @param       string          $value          node value
	 * @return      string          modified node value with replacement placeholders
	 */
	protected function parseSmiley(\DOMText $text, $value) {
		static $smileyPatterns = null;
		if ($smileyPatterns === null) {
			$smileyPatterns = [];
			$codes = [
				'simple' => [],
				'difficult' => []
			];
			
			foreach ($this->smilies as $smileyCode => $smiley) {
				$smileyCode = preg_quote($smileyCode, '~');
				
				if (preg_match('~^\\\:.+\\\:$~', $smileyCode)) {
					$codes['simple'][] = $smileyCode;
				}
				else {
					$codes['difficult'][] = $smileyCode;
				}
			}
			
			$index = 0;
			$tmp = '';
			$length = 0;
			foreach ($codes['simple'] as $code) {
				if (!empty($tmp)) $tmp .= '|';
				$tmp .= $code;
				
				// add `1` to account for `|` (btw we end up off by 1, but who cares)
				$length += (mb_strlen($code) + 1);
				
				// start a new pattern after 30k characters
				if ($length > 30000) {
					$smileyPatterns[$index] = $tmp;
					
					$tmp = '';
					$index++;
					$length = 0;
				}
			}
			
			if (!empty($codes['difficult'])) {
				if (!empty($tmp)) $tmp .= '|';
				$tmp .= '(?<=\s|^)(?:';
				$atStart = true;
				
				foreach ($codes['difficult'] as $code) {
					if ($atStart) $atStart = false;
					else $tmp .= '|';
					
					$tmp .= $code;
					
					// add `1` to account for `|` (btw we end up off by 1, but who cares)
					$length += (mb_strlen($code) + 1);
					
					// start a new pattern after 30k characters
					if ($length > 30000) {
						// close the pattern group
						$tmp .= ')(?=\s|$)';
						
						$smileyPatterns[$index] = $tmp;
						
						$atStart = true;
						$tmp = '(?<=\s|^)(?:';
						$index++;
						$length = 0;
					}
				}
				
				if (!empty($tmp)) {
					if ($tmp === '(?<=\s|^)(?:') {
						$tmp = '';
					}
					else {
						// close the pattern group
						$tmp .= ')(?=\s|$)';
					}
				}
			}
			
			if (!empty($tmp)) {
				$smileyPatterns[$index] = $tmp;
			}
			
			for ($i = 0, $length = count($smileyPatterns); $i < $length; $i++) {
				$smileyPatterns[$i] = '~(' . $smileyPatterns[$i] . ')~';
			}
		}
		
		foreach ($smileyPatterns as $smileyPattern) {
			$value = preg_replace_callback($smileyPattern, function ($matches) use ($text) {
				$smileyCode = $matches[0];
				if ($this->smileyCount === 50) {
					return $smileyCode;
				}
				
				$this->smileyCount++;
				$smiley = $this->smilies[$smileyCode];
				$element = $text->ownerDocument->createElement('img');
				$element->setAttribute('src', $smiley->getURL());
				$element->setAttribute('class', 'smiley');
				$element->setAttribute('alt', $smileyCode);
				$element->setAttribute('height', $smiley->getHeight());
				if ($smiley->getURL2x()) {
					$element->setAttribute('srcset', $smiley->getURL2x() . ' 2x');
				}
				
				return $this->addReplacement($text, $element);
			}, $value);
		}
		
		return $value;
	}
	
	/**
	 * Replaces all found occurrences of special text with their new value.
	 * 
	 * @param       \DOMText        $text           text node
	 * @param       \DOMElement[]   $elements       elements to be inserted
	 */
	protected function replaceMatches(\DOMText $text, array $elements) {
		$nodes = [$text];
		
		foreach ($elements as $marker => $element) {
			for ($i = 0, $length = count($nodes); $i < $length; $i++) {
				/** @var \DOMText $node */
				$node = $nodes[$i];
				$value = $node->textContent;
				
				if (($pos = mb_strpos($value, $marker)) !== false) {
					// move text in front of the marker into a new text node,
					// unless the position is 0 which means there is nothing
					if ($pos !== 0) {
						$newNode = $node->ownerDocument->createTextNode(mb_substr($value, 0, $pos));
						$node->parentNode->insertBefore($newNode, $node);
						
						// add new text node to the stack as it may contain other markers
						$nodes[] = $newNode;
						$length++;
					}
					
					$node->parentNode->insertBefore($element, $node);
					
					// modify text content of existing text node
					$node->nodeValue = mb_substr($value, $pos + strlen($marker));
				}
			}
		}
	}
	
	/**
	 * Returns true if text node is inside a code element, suppresing any
	 * auto-detection of content.
	 * 
	 * @param       \DOMText        $text           text node
	 * @return      boolean         true if text node is inside a code element
	 */
	protected function hasCodeParent(\DOMText $text) {
		$parent = $text;
		/** @var \DOMElement $parent */
		while ($parent = $parent->parentNode) {
			$nodeName = $parent->nodeName;
			if ($nodeName === 'code' || $nodeName === 'kbd' || $nodeName === 'pre') {
				return true;
			}
			else if ($nodeName === 'woltlab-metacode' && in_array($parent->getAttribute('data-name'), $this->sourceBBCodes)) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Returns true if text node is inside a link, preventing the link content
	 * being recognized as a link again.
	 * 
	 * @param       \DOMText        $text           text node
	 * @return      boolean         true if text node is inside a link
	 */
	protected function hasLinkParent(\DOMText $text) {
		$parent = $text;
		/** @var \DOMElement $parent */
		while ($parent = $parent->parentNode) {
			$nodeName = $parent->nodeName;
			if ($nodeName === 'a') {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Uses string markers to replace the matched text. This process prevents multiple
	 * detections being applied to the same target and enables us to delay replacement.
	 * 
	 * Immediately replacing matches would potentially cause a lot of DOM modifications
	 * and moving of nodes especially if there are multiple matches per text node.
	 * 
	 * @param       \DOMText        $text           text node
	 * @param       \DOMElement     $element        element queued for insertion
	 * @return      string          replacement marker
	 */
	public function addReplacement(\DOMText $text, \DOMElement $element) {
		$index = array_search($text, $this->nodeStack, true);
		if ($index === false) {
			$index = count($this->nodeStack);
			
			$this->nodeStack[$index] = $text;
			$this->elementStack[$index] = [];
		}
		
		$marker = $this->getNewMarker();
		$this->elementStack[$index][$marker] = $element;
		
		return $marker;
	}
	
	/**
	 * Returns a random string marker for replacement.
	 * 
	 * @return      string          random string marker
	 */
	public function getNewMarker() {
		return '@@@' . StringUtil::getUUID() . '@@@';
	}
	
	/**
	 * Returns the username for the given regular expression match and takes care
	 * of any quotes outside the username and certain special characters, such as
	 * colons, that have been incorrectly matched.
	 * 
	 * @param	string		$match          matched username
	 * @return	string          sanitized username
	 */
	public function getUsername($match) {
		// remove escaped single quotation mark
		$match = str_replace("''", "'", $match);
		
		// remove single quotation marks
		if ($match{0} == "'") {
			$match = mb_substr($match, 1, -1);
		}
		else {
			// remove characters that might be at the end of our match
			// but are not part of the username itself such as a colon
			// rtrim() is not binary safe
			$match = preg_replace('~[:;,.)]$~', '', $match);
		}
		
		return mb_strtolower($match);
	}
}
