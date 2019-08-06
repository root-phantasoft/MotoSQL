#!/usr/bin/php
<?php
error_reporting( error_reporting() & ~E_NOTICE );
ini_set("log_errors", 1);
ini_set("error_log", "/var/log/motosql.log");
$opt=getopt("",array("no-daemon"));
function e($str){
    if(isset($opt['no-daemon'])){
	echo $str . "\n";
    }
    error_log($str);
}

if(!isset($opt['no-daemon'])){
    //Set the ticks
    declare(ticks = 1);

    //Fork the current process
    $processID = pcntl_fork();

    //Check to make sure if forked ok.
    if ( $processID == -1 ) {
	echo "\n Error:  The process failed to fork. \n";
    } else if ( $processID ) {
	//This is the parent process.
	exit;
    } else {
	//We're now in the child process.
	e("Daemon starting. ");
    }

    //Now, we detach from the terminal window, so that we stay alive when
    //it is closed.
    if ( posix_setsid() == -1 ) {
	echo "\n Error: Unable to detach from the terminal window. \n";
    }

    //Get out process id now that we've detached from the window.
    $posixProcessID = posix_getpid();

    //Create a new file with the process id in it.
    $filePointer = fopen( "/var/run/motosql.pid" , "w" );
    fwrite( $filePointer , $posixProcessID );
    fclose( $filePointer );
}

//Daemon code


function signal_handler($signal) {
    e("Received signal $signal, exiting.");
    exit(1);
}

pcntl_signal(SIGTERM, 'signal_handler');

mb_internal_encoding("UTF-8");
require_once('RedditBot.php');
$db = new PDO("mysql:host=localhost;dbname=MotoSQL", 'motosql', 'JIOQWF98HREYF45EUYHTK~E84UG~~34YHG938YGF');

// Get races/classes/events
// SELECT r.year,r.shortname,r.circuit,rc.name AS class, rce.name AS session FROM races r, race_categories rc, race_categories_events rce WHERE rc.id_race=r.id AND rce.id_race_category=rc.id;

function findRace($circuit,$year){  
    global $db;
    $circuit = iconv("utf-8", "ascii//TRANSLIT", str_replace("'","",$circuit));
    if(is_numeric($circuit)){
	$params=array(':circuit' => $circuit, ':year' => $year);
    }else{
	$params=array(':circuit' => '%' . $circuit . '%', ':year' => $year);
    }
    $get=$db->prepare('SELECT * FROM `races` WHERE `year`=:year AND (`shortname` LIKE :circuit OR `circuit` LIKE :circuit OR `sequence`=:circuit)');
    $get->execute($params);
    $res=$get->fetchAll(PDO::FETCH_ASSOC);
    if(count($res)){
	return $res[0];
    }else{
	return false;
    }
}

function getCategories($id_race){
    global $db;
    $params=array(':id_race' => $id_race);
    $get=$db->prepare("SELECT name,id FROM race_categories WHERE id_race=:id_race");
    $get->execute($params);
    $cats=$get->fetchAll(PDO::FETCH_KEY_PAIR);
    return $cats;
}

function getSessions($id_category){
    global $db;
    $params=array(':id_category' => $id_category);
    $get=$db->prepare("SELECT name,id FROM race_categories_events WHERE id_race_category=:id_category");
    $get->execute($params);
    $sessions=$get->fetchAll(PDO::FETCH_KEY_PAIR);
    return $sessions;
}

function getResults($eid,$limit){
    global $db;
    //$params=array(':eid',$eid, ':limit' => $limit); 
    $get=$db->prepare("SELECT pos, points, rider_num, rider_name, rider_bike, speed, time, gap FROM results WHERE id_race_categories_events=:eid ORDER BY pos=0,pos ASC LIMIT :limit");
    $get->bindValue(':eid',$eid);
    $get->bindValue(':limit',(int) $limit, PDO::PARAM_INT);
    $get->execute();
    $res=$get->fetchAll(PDO::FETCH_ASSOC);
    return $res;  
}

function getWinner($m){
    $race=(findRace($m['circuit'],$m['year']));
    if(!$race){
	return "I couldn't find that race!";
    }
    if($race){
	$out="The {$race['year']} {$race['title']} was won by ";
	$cats=getCategories($race['id']);
	$cat=0;
	foreach($cats as $class => $class_id){
	    $cat++;
	    $sessions=getSessions($class_id);
	    $res=getResults($sessions['RACE'],1);
	    $v=$res[0];
	    if($v['rider_num']){
		$rider_name="**\#{$v['rider_num']}** {$v['rider_name']}";
	    }else{
		$rider_name="{$v['rider_name']}";
	    }
	    $rider_name = iconv("utf-8", "ascii//TRANSLIT", $rider_name);
	    $str="$rider_name in $class";
	    if($cat==1){
		$out.=" $str";
	    }elseif($cat==count($cats)){
		$out.=" and $str.";
	    }else{
		$out.=", $str";
	    }
	}
    }
    return $out;
}

function resultsTable($m,$limit){
    $req_session=$m['session'] ? strtoupper($m['session']) : "RACE";
    $req_class=strtoupper($m['class']);
    $race=(findRace($m['circuit'],$m['year']));
    if(!$race){
	return "I couldn't find that race!";
    }
    $out="#Results for {$race['title']} {$race['year']}\n";
    $out.="**Circuit:** {$race['circuit']}\n";
    $out.="**Round:** {$race['sequence']}\n";
    $out.="**Short Name:** {$race['shortname']}\n";
    $out.="*****\n";
    if($race){
	$cats=getCategories($race['id']);
	foreach($cats as $class => $class_id){
	    if($req_class=="" || $req_class=="ALL" || $req_class==strtoupper($class)){
		$sessions=getSessions($class_id);
		$out.="\n\n###$class  -  $req_session\n";
		
		$res=getResults($sessions[$req_session],$limit);
		$out.="Position|Rider|Bike|Points|Speed|Time\n";
		$out.=":--:|:---|:--:|:--:|:--:|:---|\n";
		foreach($res as $k => $v){
		    if($v['rider_num']){
			$rider_name="**\#{$v['rider_num']}** {$v['rider_name']}";
		    }else{
			$rider_name="{$v['rider_name']}";
		    }
		    $rider_name = iconv("utf-8", "ascii//TRANSLIT", $rider_name);
		    $out.="{$v['pos']}|$rider_name|{$v['rider_bike']}|{$v['points']}|{$v['speed']}|{$v['time']}\n";
		}
		
	    }
	}
    }
    return $out;
}

function process_query($query){
    global $db;
    // REFERENCE
    // [RESULTS/WHO WON/WINNERS/1st/2nd/3rd/nth] [SHORTNAME/CIRCUIT/ROUND#] IN [YEAR]
    preg_match("/((?<class>ALL|MOTOGP|MOTO2|MOTO3|500CC|350CC|250CC|125CC|50CC|80CC){0,1}(\ ){0,1}(?<session>FP|FP1|FP2|FP3|FP4|Q1|Q2|QP|RACE|WUP){0,1}(\ ){0,1}(?<query>RESULTS|FULL RESULTS|WHO\ WON)(\ FOR){0,1})\ (?<circuit>[A-z0-9]*|[\"\'][A-z0-9\ ]*[\"\'])\ (IN\ ){0,1}(?<year>[0-9]{4})/i",$query,$m); 
    if($m){
	switch(strtoupper($m['query'])){
	    case 'WHO WON':
	    case 'WINNER':
	    case 'WINNERS':
		return getWinner($m);
		break;
	    case 'RESULTS':
		return resultsTable($m,3);
		break;
	    case 'FULL RESULTS':	
		return resultsTable($m,100);
		break;
	}
    }/*else{
	preg_match("/(?<query>CHART CHAMPIONSHIP)(\ ){0,1}(?<year_start>[0-9]{4})(-){0,1}(?<year_end>[0-9]{4})(\ FOR\ ){0,1}(?<riders>([A-Z\ ,]+))+/i",$query,$m);
	print_r($m);exit;
	}  */  
    // RACES IN [YEAR] CLASS:[CLASS] RIDER:[RIDER] RIDERNUM: [RIDERNUM] 
    return false;
}

//echo process_query(implode(" ",array_slice($argv,1,$argc-1)));exit;

$b = new RedditBot();

//Check for mail

while(true){
    pcntl_signal_dispatch();
    e("Checking for messages ...");
    $user=$b->getUser();
    if($user->has_mail){
	e("We've got mail!");
	$mentions=$b->getMentions()->data->children;
	foreach($mentions as $k => $v){
	    if($v->data->new){
		e("New mention ID: " . $v->data->id);
		// parse message      
		preg_match('/\/u\/motosql\ ([a-zA-Z0-9\ \']*)/',$v->data->body,$matches);
		if($matches[1]){
		    $query=$matches[1];
		    $res=process_query($query);
		    if(!$res){
			e("ID: " . $v->data->id . " | Invalid query: " . $query);
			$res="Sorry, I couldn't understand your query!";
		    }
		    e("ID: " . $v->data->id . " | : " . $query);		    
		    $b->addComment("t1_".$v->data->id,$res);
		    $b->markRead("t1_".$v->data->id);	  
		}else{
	    	    e("ID: " . $v->data->id . " | Parse error for query: " . $query);
		    $b->addComment("t1_".$v->data->id,"Sorry, I couldn't understand your query!");
		    $b->markRead("t1_".$v->data->id);
		}
	    }
	}
    }
    sleep(3);
    
}

?>
