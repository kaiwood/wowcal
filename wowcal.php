<?php

define('SCRIPT_NAME', $_SERVER['argv'][0]);
define('SCRIPT_VERSION', '1.0');

define('URL_BASE_ARMORY', 'http://www.wowarmory.com/');
define('URL_BASE_LOGIN', 'https://us.battle.net/');

define('URL_CALENDAR_USER', 'vault/calendar/month-user.json');
define('URL_CALENDAR_WORLD', 'vault/calendar/month-world.json');
define('URL_CALENDAR_DETAIL', 'vault/calendar/detail.json');

define('URL_LOGIN', 'login/login.xml');

$WoWCal = new WoWCal;

class WoWCal_Event {
		
	private $calendar_type;
	
	public $summary;
	public $start;	
	
	public $description;
	
	public function __construct($event) {		
		$this->calendar_type = $event->calendarType;
		$this->summary = $event->summary;
		$this->start = floor($event->start / 1000);
	}
		
}

class WoWCal_WorldEvent extends WoWCal_Event {
	
	public $end;
		
	public function __construct($event) {
		parent::__construct($event);
		
		$this->description = $event->description;
		$this->end = floor($event->end / 1000);
	}
	
}

class WoWCal_UserEvent extends WoWCal_Event {
	
	private $STATUSES = array(
		'signedUp' => 'Signed Up',
		'notSignedUp' => 'Not Signed Up',
		'confirmed' => 'Confirmed',
		'invited' => 'Invited',
		'available' => 'Available',
	);
	
	private $id;
	private $type;
	public $locked = false;
		
	public $owner;
	public $moderator = false;
	
	public $inviter;
	public $status;
	
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

class WoWCal_Invitee {
	
	private $STATUSES = array(
		'signedUp' => 'Signed Up',
		'notSignedUp' => 'Not Signed Up',
		'confirmed' => 'Confirmed',
		'invited' => 'Invited',
		'available' => 'Available',
	);
	
	public $name;
	public $moderator = false;
	
	public $status;	
	
	public function __construct($invitee) {
		$this->name = $invitee->invitee;
		$this->moderator = ($invitee->moderator) ? true : false;
		
		$this->status = $this->STATUSES[$invitee->status];
	}
	
}

class WoWCal {
	
	private $REQUIRED_OPTIONS = array(
		'username',
		'password',
		'character',
		'realm',
	);
	
	private $CALENDAR_TYPES_WORLD = array(
		'player' => 'player',
		'holiday' => 'holiday',
		'bg' => 'bg',
		'darkmoon' => 'darkmoon',
		'raid_lockout' => 'raidLockout',
		'raid_reset' => 'raidReset',
		'holiday_weekly' => 'holidayWeekly',
	);
	private $CALENDAR_TYPES_USER = array(
		'raid',
		'dungeon',
		'pvp',
		'meeting',
		'other',
	);
	
	private $COOKIE;
	private $LOG;
	
	private $verbose = false;
	
	private $log_file;
	private $calendar_file;
	
	private $month;
	private $year;
	private $default_world_calendars = true;
	private $world_calendars = array(
		'player',
	);
	private $default_user_calendars = true;
	private $user_calendars = array(
		'dungeon',
		'meeting',
		'other',
		'pvp',
		'raid',
	);
	
	private $username;
	private $password;
	private $character;
	private $realm;
	
	public function __construct() {		
		$this->month = date('n');
		$this->year = date('Y');
		
		$this->parse_options();	
				
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
	
	private function login() {		
		$this->log('Logging In...');
		
		$post = array(
			'accountName' => $this->username,
			'password' => $this->password,
			'ref' => URL_BASE_ARMORY.'index.xml',
			'app' => 'armory',
		);		
		$response = $this->request(URL_BASE_LOGIN.URL_LOGIN, $post);		
		
		if(strstr($response, 'error.form.login')) {
			$this->log('Login Failed! Exiting.');
			$this->quit();
		} else $this->log('Login Successful!');
	}
	
	private function get_user_calendar_events() {
		$post = array(
			'month' => $this->month,
			'year' => $this->year,
		);
		$response = $this->request(URL_BASE_ARMORY.URL_CALENDAR_USER, $post);
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
	
	private function get_world_calendar_events() {
		$events = array();
		foreach($this->world_calendars as $type) {
			$type = strtolower($type);
			
			if(in_array($type, array_keys($this->CALENDAR_TYPES_WORLD))) {
				$this->log('Retreiving World Calendar: '.$type.'...');
		
				$post = array(
					'type' => $this->CALENDAR_TYPES_WORLD[$type],
					'month' => $this->month,
					'year' => $this->year,
				);				
				$response = $this->request(URL_BASE_ARMORY.URL_CALENDAR_WORLD, $post);
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
	
	private function get_event_detail($id) {
		$response = $this->request(URL_BASE_ARMORY.URL_CALENDAR_DETAIL, array('e' => $id));
		return $this->json($response);
	}
	
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
	
	private function json($string) {
		return json_decode(substr($string, 13, -2));
	}
	
	private function request($url, $parameters = array()) {
		if(!$this->COOKIE)
			$this->COOKIE = tempnam("/tmp", "CURLCOOKIE");
		
		$defaults = array(
			'cn' => $this->character,
			'r' => $this->realm,
		);
		$parameters = array_merge($defaults, $parameters);
		
		$post = null;
		foreach($parameters as $parameter => $value)
			$post .= $parameter.'='.$value.'&';
		$post = substr($post, 0, -1);
								
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url.'?'.$post);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 Gecko/20070219 Firefox/2.0.0.2');
		
		curl_setopt($ch, CURLOPT_COOKIESESSION, true);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->COOKIE);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->COOKIE);		
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		
		if($post) {
			curl_setopt($ch, CURLOPT_POST, true);
		}
		
		$response = curl_exec($ch);		
		curl_close($ch);

		return $response;
	}
	
	private function log($message) {
		$message = date('M d H:i:s').": ".$message."\n";		
		$this->LOG .= $message;
		
		if($this->verbose) echo $message;
	}
	
	private function quit() {
		if($this->log_file) {			
			$this->log('Writing Log to File: '.$this->log_file);
			file_put_contents($this->log_file, $this->LOG);
		}
		exit();
	}
	
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