<?php
/**
 * WoWCal: Export World of Warcraft Calendar to iCal.
 * 
 * Converts JSON formatted data requested from the World of Warcraft Armory into basic iCal format.
 * 
 * PHP 4 >= 4.0.2, PHP 5
 * 
 * Ryon Sherman (http://ryonsherman.wordpress.com)
 * Copyright 2009, Ryon Sherman (ryon.sherman@gmail.com)
 * 
 * Licensed under the MIT License
 * Redistributions of files must retain the above copyright notice.
 * 
 * @filesource
 * @copyright 	Copyright 2009, Ryon Sherman (ryon.sherman@gmail.com)
 * @link 		http://ryonsherman.wordpress.com Ryon Sherman's Blog
 * @package 	wowcal
 * @version 	1.0.2
 * @license 	http://www.opensource.org/licenses/mit-license.php The MIT License
 */

define('SCRIPT_NAME', $_SERVER['argv'][0]);
define('SCRIPT_VERSION', '1.0.2');

define('URL_BASE_ARMORY', 'http://www.wowarmory.com/');
define('URL_BASE_ARMORY_EU', 'http://eu.wowarmory.com/');
define('URL_BASE_LOGIN', 'https://us.battle.net/');

define('URL_CALENDAR_USER', 'vault/calendar/month-user.json');
define('URL_CALENDAR_WORLD', 'vault/calendar/month-world.json');
define('URL_CALENDAR_DETAIL', 'vault/calendar/detail.json');

define('URL_LOGIN', 'login/login.xml');

$WoWCal = new WoWCal;

/**
 * Core Event class.
 * 
 * Creates a basic placeholder event.
 * 
 * @package 	wowcal
 */
class WoWCal_Event {
		
	/**
	 * Calendar type.
	 * 
	 * @var		string
	 * @access	private
	 */
	private $calendar_type;
	
	/**
	 * Event description.
	 * 
	 * @var		string
	 * @access	public
	 */
	public $description;
	
	/**
	 * Event summary.
	 * 
	 * @var		string
	 * @access	public
	 */
	public $summary;
	
	/**
	 * Event start timestamp.
	 * 
	 * @var		integer
	 * @access	public
	 */
	public $start;	
	
	/**
	 * Constructor. 
	 * Assigns default values for event.
	 * 
	 * @param	object	$event	
	 * @access	public
	 */
	public function __construct($event) {
		$this->calendar_type = $event->calendarType;
		$this->summary = $event->summary;
		$this->start = floor($event->start / 1000);
	}
		
}

/**
 * World Event Class.
 * 
 * Extends Core Event. Includes world event details.
 * 
 * @package		wowcal
 */
class WoWCal_WorldEvent extends WoWCal_Event {
	
	/**
	 * Event end timestamp.
	 * 
	 * @var		integer
	 * @access	public
	 */
	public $end;
		
	/**
	 * Constructor. 
	 * Assigns extended values for world event.
	 * 
	 * @param	object	$event	
	 * @access	public
	 */
	public function __construct($event) {
		parent::__construct($event);
		
		$this->description = $event->description;
		$this->end = floor($event->end / 1000);
	}
	
}

/**
 * User Event Class.
 * 
 * Extends Core Event. Includes user event details.
 * 
 * @package		wowcal
 */
class WoWCal_UserEvent extends WoWCal_Event {
	
	/**
	 * Status translation table.
	 * 
	 * @var		array
	 * @access	private
	 */
	private $STATUSES = array(
		'signedUp' => 'Signed Up',
		'notSignedUp' => 'Not Signed Up',
		'confirmed' => 'Confirmed',
		'invited' => 'Invited',
		'available' => 'Available',
	);
	
	/**
	 * Event identification number.
	 * 
	 * @var		integer
	 * @access	private
	 */
	private $id;
	
	/**
	 * Name of event type.
	 * 
	 * @var		string
	 * @access	private
	 */
	private $type;
	
	/**
	 * Event lockout toggle.
	 * 
	 * @var		boolean
	 * @access	public
	 */
	public $locked = false;
		
	/**
	 * Name of event owner.
	 * 
	 * @var		string
	 * @access	public
	 */
	public $owner;
	/**
	 * Name of event inviter.
	 * 
	 * @var		string
	 * @access	public
	 */
	public $inviter;
	/**
	 * Moderator status.
	 * True if you are a moderator of the event.
	 * 
	 * @var		boolean
	 * @access	public
	 */
	public $moderator = false;
	
	/**
	 * Your status to the event.
	 * 
	 * @var		string
	 * @access	public
	 */
	public $status;
	
	/**
	 * Constructor. 
	 * Assigns extended values for user event. 
	 */
	public function __construct($event, $detail = null) {
		parent::__construct($event);
		
		$this->id = $event->id;		
		$this->type = $event->type;
		$this->locked = ($detail->locked) ? true : false;
		
		$this->description = $detail->description;
				
		$this->owner = $event->owner;
		$this->moderator = ($event->moderator) ? true : false;
				
		$this->inviter = @$event->inviter;
		$this->status = $this->STATUSES[$event->status];
		
		foreach($detail->invites as $invitee) {
			$this->invitees[] = new WoWCal_Invitee($invitee);
		}	
	}
		
}

/**
 * Event invitee class.
 * 
 * Creates an object of event invitee details.
 * 
 * @package		wowcal
 */
class WoWCal_Invitee {
	
	/**
	 * Status translation table.
	 * 
	 * @var		array
	 * @access	private
	 */
	private $STATUSES = array(
		'signedUp' => 'Signed Up',
		'notSignedUp' => 'Not Signed Up',
		'confirmed' => 'Confirmed',
		'invited' => 'Invited',
		'available' => 'Available',
	);
	
	/**
	 * Invitee name.
	 * 
	 * @var		string
	 * @access	public
	 */
	public $name;
	/**
	 * Moderator status.
	 * True if the invitee is a moderator of the event.
	 * 
	 * @var		boolean
	 * @access	public
	 */
	public $moderator = false;
	
	/**
	 * Invitees status to the event.
	 * 
	 * @var		string
	 * @access	public
	 */
	public $status;	
	
	/**
	 * Constructor. 
	 * Assigns default values for invitee.
	 */
	public function __construct($invitee) {
		$this->name = $invitee->invitee;
		$this->moderator = ($invitee->moderator) ? true : false;
		
		$this->status = $this->STATUSES[$invitee->status];
	}
	
}

/**
 * Main Driver class.
 * 
 * Creates an object of methods used to export JSON formatted data from
 * the World of Warcraft Armory into iCal format.
 * 
 * @package		wowcal
 */
class WoWCal {
	
	/**
	 * Required options.
	 * 
	 * @var		array
	 * @access 	private
	 */
	private $REQUIRED_OPTIONS = array(
		'username',
		'password',
		'character',
		'realm',
	);
	
	/**
	 * World calendar translation table.
	 * 
	 * @var		array
	 * @access	private
	 */
	private $CALENDAR_TYPES_WORLD = array(
		'player' => 'player',
		'holiday' => 'holiday',
		'bg' => 'bg',
		'darkmoon' => 'darkmoon',
		'raid_lockout' => 'raidLockout',
		'raid_reset' => 'raidReset',
		'holiday_weekly' => 'holidayWeekly',
	);
	/**
	 * User calendar translation table.
	 * 
	 * @var		array
	 * @access	private
	 */
	private $CALENDAR_TYPES_USER = array(
		'raid',
		'dungeon',
		'pvp',
		'meeting',
		'other',
	);
	
	/**
	 * Internal cookie storage.
	 * 
	 * @var		string
	 * @access	private
	 */
	private $COOKIE;
	
	/**
	 * Internal log storage.
	 * 
	 * @var		string
	 * @access	private
	 */
	private $LOG;
	
	/**
	 * Verbose output.
	 * 
	 * @var		boolean
	 * @access	private
	 */
	private $verbose = false;
	
	/**
	 * Use european realms.
	 * 
	 * @var		boolean
	 * @access	private
	 */
	private $european = false;
	
	/**
	 * Armory URL base.
	 * 
	 * @var		string
	 * @access	private
	 */
	private $url_base_armory;
	
	/**
	 * Log file name.
	 * 
	 * @var		string
	 * @access	private
	 */
	private $log_file;
	/**
	 * Calendar file name.
	 * 
	 * @var		string
	 * @access	private
	 */
	private $calendar_file;
	
	/**
	 * Calendar month.
	 * 
	 * @var		integer
	 * @access	private
	 */
	private $month;
	/**
	 * Calendar year
	 * 
	 * @var		integer
	 * @access	private
	 */
	private $year;
	
	/**
	 * Default world calendars toggle.
	 * Determine if default world calendars should be used.
	 * 
	 * @var		boolean
	 * @access	private
	 */
	private $default_world_calendars = true;
	/**
	 * List of default world calendars.
	 * 
	 * @var		array
	 * @access	private
	 */
	private $world_calendars = array(
		'player',
	);
	/**
	 * Default user calendars toggle.
	 * Determine if default user calendars should be used.
	 * 
	 * @var		boolean
	 * @access	private
	 */
	private $default_user_calendars = true;
	/**
	 * List of default user calendars.
	 * 
	 * @var		array
	 * @access	private
	 */
	private $user_calendars = array(
		'dungeon',
		'meeting',
		'other',
		'pvp',
		'raid',
	);
	
	/**
	 * Account username.
	 * 
	 * @var		string
	 * @access	private
	 */
	private $username;
	/**
	 * Account password.
	 * 
	 * @var		string
	 * @access	private
	 */
	private $password;
	/**
	 * Character name.
	 * 
	 * @var		string
	 * @access	private
	 */
	private $character;
	/**
	 * Character realm.
	 * 
	 * @var		string
	 * @access	private
	 */
	private $realm;
	
	/**
	 * Constructor.  
	 * Assigns default values for class.
	 * 
	 * @access public
	 */
	public function __construct() {		
		$this->month = date('n');
		$this->year = date('Y');
		
		$this->parse_options();	
		
		if ($this->european) {
			$this->url_base_armory = URL_BASE_ARMORY_EU;
		} else {
			$this->url_base_armory = URL_BASE_ARMORY;
		}
				
		$this->log('WoWCal Started.');		
		$this->login();		
		
		$user_events = array();
		if(!empty($this->user_calendars))
			$user_events = $this->get_user_calendar_events();		
		$world_events = array();
		if(!empty($this->world_calendars))
			$world_events = $this->get_world_calendar_events();		
		$events = array_merge($user_events, $world_events);
				
		$this->create_ical($events);		
		$this->quit();
		$this->log('WoWCal Completed.');
	}
		
	/**
	 * Parse options.
	 * Execute specific functions based on user input.
	 * 
	 * @access private
	 */
	private function parse_options() {
		if($_SERVER['argc'] <= 1) $this->print_usage();
		
		for($i = 1; $i < $_SERVER['argc']; $i++) {
			switch($_SERVER['argv'][$i]) {
				case '-u': case '--username': $this->username = $_SERVER['argv'][++$i]; break;
				case '-p': case '--password': $this->password = $_SERVER['argv'][++$i]; break;
				case '-c': case '--character': $this->character = $_SERVER['argv'][++$i]; break;
				case '-r': case '--realm': $this->realm = $_SERVER['argv'][++$i]; break;
				
				case '-f': case '--file':
					$this->calendar_file = $_SERVER['argv'][++$i];
				break;
				case '-l': case '--logfile':
					$this->log_file = $_SERVER['argv'][++$i];
				break;

				case '-m': case '--month': $this->month = intval($_SERVER['argv'][++$i]); break;
				case '-y': case '--year': $this->year = intval($_SERVER['argv'][++$i]); break;
				case '-ut': case '--user-type':
					$this->user_calendars = explode(',', $_SERVER['argv'][++$i]);
					$this->default_user_calendars = false;
					
					if($this->default_world_calendars)
						$this->world_calendars = array();
				break;				
				case '-wt': case '--world-type':
					$this->world_calendars = explode(',', $_SERVER['argv'][++$i]);
					$this->default_world_calendars = false;
					
					if($this->default_user_calendars)
						$this->user_calendars = array();
				break;
				
				case '-e': case '--european': $this->european = true; break;
				
				case '-v': case '--verbose': $this->verbose = true; break;				
				
				case '-V': case '--version':
					print SCRIPT_NAME." ".$this->SCRIPT_VERSION."\n";
					$this->quit();
				break;
				case '-h': case '--help': $this->print_usage(true); break;
				default: $this->print_usage(); break;
			}
		}
		
		foreach($this->REQUIRED_OPTIONS as $option) {
			if(!$this->$option) {
				print SCRIPT_NAME.": missing ".$option."\n";
				$this->print_usage();
			}
		}
	}
	
	/**
	 * Login.
	 * Perform login action.
	 * 
	 * @access private
	 */
	private function login() {		
		$this->log('Logging In...');
		
		$parameters = array(
			'accountName' => $this->username,
			'password' => $this->password,			
			'ref' => $this->url_base_armory.'index.xml',
			'app' => 'armory',
		);	
		$response = $this->request(URL_BASE_LOGIN.URL_LOGIN, $parameters);		
		
		if(empty($response) or strstr($response, 'error.form.login')) {
			$this->log('Login Failed! Exiting.');
			$this->quit();
		} else $this->log('Login Successful!');
	}
	
	/**
	 * Get User Calendar Events.
	 * Retreive user calendar events.
	 * 
	 * @return	array	events
	 * @access	private
	 */
	private function get_user_calendar_events() {
		$parameters = array(
			'month' => $this->month,
			'year' => $this->year,
		);
		$response = $this->request($this->url_base_armory.URL_CALENDAR_USER, $parameters);
		
		if(strstr($response, 'layout/maintenance.xsl')) {
			$this->log('Armory currently under maintenance...Please try again later');
			$this->quit();
		}
		
		$json = $this->json($response);
		
		$events = array();
		if(is_object($json)) {
			if($json->events) {
				foreach($this->user_calendars as $type) {
					$type = strtolower($type);
					
					if(in_array($type, $this->CALENDAR_TYPES_USER)) {
						$this->log('Retreiving User Calendar: '.$type.'...');
												
						$selected_events = array();
						foreach($json->events as $event) {
							if($event->type == $type)
								$selected_events[] = $event;
						}
						
						$this->log('Found '.count($selected_events).' Events...');
				
						foreach($selected_events as $event)
							$events[] = new WoWCal_UserEvent($event, $this->get_event_detail($event->id));
					
						$this->log('User Calendar Retreived.');
					} else $this->log('ALERT: Invalid user calendar type selected.');
				}
			} else $this->log('No Events Found.');
		} else $this->log('Invalid JSON received. Continuing.');
		
		return $events;
	}
	
	/**
	 * Get World Calendar Events.
	 * Retreive world calendar events.
	 * 
	 * @return	array	events
	 * @access	private
	 */
	private function get_world_calendar_events() {
		$events = array();
		foreach($this->world_calendars as $type) {
			$type = strtolower($type);
			
			if(in_array($type, array_keys($this->CALENDAR_TYPES_WORLD))) {
				$this->log('Retreiving World Calendar: '.$type.'...');
		
				$parameters = array(
					'type' => $this->CALENDAR_TYPES_WORLD[$type],
					'month' => $this->month,
					'year' => $this->year,
				);				
				$response = $this->request($this->url_base_armory.URL_CALENDAR_WORLD, $parameters);
				
				if(strstr($response, 'layout/maintenance.xsl')) {
					$this->log('Armory currently under maintenance...Please try again later');
					$this->quit();
				}
		
				$json = $this->json($response);
				
				if(is_object($json)) {
					if($json->events) {
						$this->log('Found '.count($json->events).' Events...');
						
						foreach($json->events as $event)
							$events[] = new WoWCal_WorldEvent($event);						
					} else $this->log('No Events Found.');
				} else $this->log('Invalid JSON received. Continuing.');
				
				$this->log('World Calendar Retreived.');
			} else $this->log('ALERT: Invalid world calendar type selected.');
		}
		
		return $events;
	}
	
	/**
	 * Get Event Detail.
	 * Retreive details on a specific event.
	 * 
	 * @param	integer	$id	Event identification number
	 * @return	object	JSON data
	 * @access	private
	 */
	private function get_event_detail($id) {
		$parameters = array(
			'e' => $id,
		);
		$response = $this->request($this->url_base_armory.URL_CALENDAR_DETAIL, $parameters);
		return $this->json($response);
	}
	
	/**
	 * Create iCal.
	 * Creates an iCal file of passed events.
	 * 
	 * @param	array	$events	Array of events
	 * @access	private
	 */
	private function create_ical($events = array()) {
		$this->log('Creating iCal ('.count($events).' events)...');
		
		$ical = "BEGIN:VCALENDAR\n";
		$ical .= "VERSION:2.0\n";
		$ical .= "PRODID:-//Mozilla.org/NONSGML Mozilla Calendar V1.1//EN\n";
		
		foreach($events as $event) {
			$ical .= "BEGIN:VEVENT\n";
			$ical .= "SUMMARY:".$event->summary."\n";
			$ical .= "DTSTART:".date('Ymd', $event->start)."T".date('His', $event->start)."\n";
			
			switch(get_class($event)) {
				case 'WoWCal_UserEvent':
					$ical .= "DTEND:".date('Ymd', $event->start)."T".date('His', $event->start)."\n";
					
					$description = $event->description.'\n\n';
					
					$description .= 'Creator: '.$event->owner.'\n';
					if($event->inviter)
						$description .= 'Inviter: '.$event->inviter.'\n';					
					
					if($event->locked)
						$description .= '\nTHIS EVENT IS LOCKED!\n';
					if($event->moderator)
						$description .= '\nYou are a moderator of this event.\n';
					$description .= '\n';					
						
					$description .= 'Your Status: '.$event->status.'\n';
					if($event->invitees) {					
						$description .= 'Other\'s Status:\n';
						foreach($event->invitees as $invitee) {
							if($invitee->moderator)
								$description .= 'Moderator - ';
							$description .= $invitee->name.' - '.$invitee->status.'\n';
						}
					}					
					
					$ical .= 'DESCRIPTION:'.$description."\n";
				break;
				
				case 'WoWCal_WorldEvent':
					$ical .= "DTEND:".date('Ymd', $event->end)."T".date('His', $event->end)."\n";
					$ical .= 'DESCRIPTION:'.str_replace(array("\n", '  '), '\n', $event->description)."\n";
				break;
				
				default: break;
			}
			
			$ical .= "END:VEVENT\n";
		}
		
		$ical .= "END:VCALENDAR\n";
		
		$file = ($this->calendar_file) ? $this->calendar_file : 'wowcal-'.$this->character.'-'.$this->realm.'.ical';

		$this->log('Writing Calendar to File ('.$file.')...');
		file_put_contents($file, $ical);
		$this->log('Calendar Exported!');
	}
	
	/**
	 * JSON.
	 * Converts JSON formatted data to stdClass object.
	 * 
	 * @param	string	$string JSON formatted string.
	 * @return	object	JSON data
	 * @access	private
	 */
	private function json($string) {
		return json_decode(substr($string, 13, -2));
	}
	
	/**
	 * Request.
	 * Performs HTTP request action.
	 * 
	 * @param	string	$url Target URL for request
	 * @param	array	$parameters Array of parameters to pass to target
	 * @return	string	Response text
	 * @access	private
	 */
	private function request($url, $parameters = array()) {
		if(!$this->COOKIE)
			$this->COOKIE = tempnam("/tmp", "CURLCOOKIE");
		
		$defaults = array(
			'cn' => $this->character,
			'r' => $this->realm,
		);
		if($parameters)
			$parameters = array_merge($defaults, $parameters);
		
		$params = null;
		foreach($parameters as $parameter => $value)
			$params .= $parameter.'='.urlencode($value).'&';
		$params = substr($params, 0, -1);
								
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url.'?'.$params);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 Gecko/20070219 Firefox/2.0.0.2');
		
		curl_setopt($ch, CURLOPT_COOKIESESSION, true);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->COOKIE);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->COOKIE);		
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
				
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
		
		$response = curl_exec($ch);
		curl_close($ch);

		return $response;
	}
	
	/**
	 * Log.
	 * Logs data.
	 * 
	 * @param	string	$message Message to write to log
	 * @access	private
	 */
	private function log($message) {
		$message = date('M d H:i:s').": ".$message."\n";		
		$this->LOG .= $message;
		
		if($this->verbose) echo $message;
	}
	
	/**
	 * Quit.
	 * Perform last minute actions and exit.
	 * 
	 * @access	private
	 */
	private function quit() {
		if($this->log_file) {			
			$this->log('Writing Log to File: '.$this->log_file);
			file_put_contents($this->log_file, $this->LOG);
		}
		exit();
	}
	
	/**
	 * Print Usage.
	 * Output script usage.
	 * 
	 * @param	boolean	$verbose	Output long or short usage
	 * @access	private
	 */
	private function print_usage($verbose = false) {		
		print ($verbose) ? 
"
WoWCal ".SCRIPT_VERSION.", a World of Warcraft calendar export tool.
Usage: ".SCRIPT_NAME." [OPTION]...

Mandatory arguments to long options are mandatory for short options too.

Startup:
	-V, --version			display the version of WoWCal and exit.
	-f, --file <file>		export to filename.
	-l, --logfile <file>		save log
	-e, --europe			use european realms.
	-v, --verbose			be verbose.
	
Battle.net:
	-u, --username <username>	account username.
	-p, --password <password>	account password.

World of Warcraft Armory:
	-c, --character	<character>	character name.
	-r, --realm <realm>		realm name.
	
Calendar:
	-m,  --month		    	selected month. M-MM. 
					* current month default.
	-y,  --year			selected year. YYYY. 
					* current year default.  
	-ut, --user-type		user calendar types. comma separated.
	     dungeon*
	     meeting*
	     other*
	     pvp*
	     raid*
	-wt, --world-type		world calendar types. comma separated.
	     bg				* indicates default.
	     darkmoon
	     holiday
	     holiday_weekly
	     player*
	     raid_lockout
	     raid_reset

Mail bug reports and suggestions to <ryon.sherman@gmail.com>
"
:		
"Usage: ".SCRIPT_NAME." [OPTION]...

Try 'php ".SCRIPT_NAME." --help' for more options.
";
	
		$this->quit();
	}
	
}

?>
