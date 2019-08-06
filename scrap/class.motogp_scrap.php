<?php
class motogp_scrap{
    protected $db;

    public function __construct($db){
	$this->db=$db;
    }
    
    public function scrap_races($year_start,$year_end){
	$db=$this->db;
	$year=$year_start;
	$year_end=$year_end ? $year_end : $year_start;
	$burl="http://www.motogp.com/en/ajax/results/selector/##YEAR##";
	$races=array();
	for($year;$year<=$year_end;$year++){
	    $r=json_decode(file_get_contents(str_replace("##YEAR##",$year,$burl)));
	    foreach($r as $k => $v){
		$params = array(':year' => $year, ':shortname' => $v->shortname, ':title' => $v->title, ':circuit' => $v->circuit, ':sequence' => $v->sequence);
		$db->prepare('
                          INSERT INTO races SET
			      `year`=:year,
			      `shortname`=:shortname,
			      `title`=:title,
			      `circuit`=:circuit,
			      `sequence`=:sequence'
		)->execute($params);
		array_push($races,$db->lastInsertId());
	    }
	}
	return $races;
    }

    public function scrap_schedule($year){
	$db=$this->db;
	$params=array('year' => $year);
	$get=$db->prepare('SELECT * FROM `races` WHERE year=:year');	       
	$get->execute($params);
	$races=$get->fetchAll(PDO::FETCH_ASSOC);
	$html=file_get_contents("http://www.motogp.com/en/calendar/");
	preg_match_all("/event\ shadow_block\ official.+?href=\"(http:\/\/www\.motogp\.com\/en\/event\/[a-z\ +]+)/is",$html,$urls);
	foreach($races as $k => $r){
	    $html=file_get_contents($urls[1][$r['sequence']-1]);
	    preg_match_all("/c-schedule__table-row.+?data-ini-time=\"(?<start>.+?)\".+?(data-end=\"(?<end>.+?)\"){0,1}c-schedule__table-cell\">(?<class>.+?)\ .+?visible-xs\">(?<session>.+?)</is",$html,$events);
	    foreach($events as $kk => $e){
		
	    }
	}
	exit;

    }

    public function scrap_race_categories($id_race){
	$db=$this->db;
	$burl="http://www.motogp.com/en/ajax/results/selector/##YEAR##/##RACE##";
	$params=array('id_race' => $id_race);
	$get=$db->prepare('SELECT * FROM `races` WHERE id = :id_race');	       
	$get->execute($params);
	$races=$get->fetchAll(PDO::FETCH_ASSOC);
	foreach($races as $k => $v){
	    $r=json_decode(file_get_contents(str_replace(array("##YEAR##",'##RACE##'),array($v['year'],$v['shortname']),$burl)));    
	    foreach($r as $kk => $vv){
		$params = array('id_race' => $v['id'], 'name' => $vv->name);
		$db->prepare('
                          INSERT INTO race_categories SET
			      `id_race`=:id_race,
			      `name`=:name'
		)->execute($params);    
	    }
	}
    }

    public function scrap_race_categories_events($id_race){
	$db=$this->db;
	$params=array('id_race' => $id_race);
	$burl="http://www.motogp.com/en/ajax/results/selector/##YEAR##/##RACE##/##CLASS##";
	$get=$db->prepare('SELECT rc.*,r.year,r.shortname FROM `race_categories` rc,`races` r WHERE r.id=rc.id_race AND rc.id_race = :id_race');
	$get->execute($params);
	$races=$get->fetchAll(PDO::FETCH_ASSOC);
	foreach($races as $k => $v){
	    $r=json_decode(file_get_contents(str_replace(array("##YEAR##",'##RACE##','##CLASS##'),array($v['year'],$v['shortname'],$v['name']),$burl)));    
	    foreach($r as $kk => $vv){
		$params = array('id_race_category' => $v['id'], 'name' => $vv->name, 'shortname' => $vv->value);
		$db->prepare('
                          INSERT INTO race_categories_events SET
			      `id_race_category`=:id_race_category,
			      `name`=:name,
			      `shortname`=:shortname'
		)->execute($params);    
	    }
	}
    }

    private function addEventMetadata($id,$attr,$value){
	$db=$this->db;
	$params=array(":id_race_categories_events" => $id, ":attribute" => $attr, ":value" => $value);
	$db->prepare('
                  INSERT INTO event_metadata SET
		      `id_race_categories_events`=:id_race_categories_events,
		      `attribute`=:attribute,
		      `value`=:value
		      ')->execute($params);
    }

    public function scrap_race_categories_events_results($id_race){
	echo "Getting results for race $id_race";
	$db=$this->db;    
	$burl="http://www.motogp.com/en/ajax/results/parse/##YEAR##/##RACE##/##CLASS##/##EVENT##";
	$params=array('id_race' => $id_race);
	$get=$db->prepare('
                       SELECT
			   rc.*,
			   r.year,
			   r.shortname,
			   re.name as event,
			   re.id as id_race_categories_events
		       FROM
			   `race_categories` rc,
			   `races` r,
			   `race_categories_events` re
		       WHERE
			   r.id=rc.id_race
			   AND re.id_race_category=rc.id
			   AND r.id = :id_race			   
			   ');
	$get->execute($params);
	$get->debugDumpParams();
	$events=$get->fetchAll(PDO::FETCH_ASSOC);
	foreach($events as $k => $v){
	    $id_event=$v['id_race_categories_events'];
	    echo "\nGetting event {$v['year']}/{$v['shortname']}/{$v['name']}/{$v['event']}";
	    $r=file_get_contents(str_replace(array("##YEAR##",'##RACE##','##CLASS##','##EVENT##'),array($v['year'],$v['shortname'],$v['name'],$v['event']),$burl));    
	    if(strlen($r)<100){
		$r=file_get_contents(str_replace(array("##YEAR##",'##RACE##','##CLASS##','##EVENT##'),array($v['year'],$v['shortname'],$v['name'],'null'),$burl));          
	    }
	    $dom = new domDocument;

	    $r=str_replace(array("</tr><td","<tbody><td"),array("</tr><tr><td","<tbody><tr><td"),$r);

	    file_put_contents("data/event_{$id_event}.html",$r);

	    @$dom->loadHTML($r);
	    $dom->preserveWhiteSpace = false;
	    $xpath = new DOMXPath($dom);

	    //Get Track Condition
	    preg_match('/Track\ Condition:\ ([A-Za-z0-9]*)\</', $r, $matches);
	    if($matches[1]){
		$this->addEventMetadata($id_event,'track_condition',$matches[1]);
	    }

	    preg_match('/Air:\ ([A-Za-z0-9º]*)\</', $r, $matches);
	    if($matches[1]){
		$this->addEventMetadata($id_event,'air',$matches[1]);
	    }

	    preg_match('/Ground:\ ([A-Za-z0-9º]*)\</', $r, $matches);
	    if($matches[1]){
		$this->addEventMetadata($id_event,'ground',$matches[1]);
	    }

	    preg_match('/Humidity:\ ([A-Za-z0-9%]*)\</', $r, $matches);
	    if($matches[1]){
		$this->addEventMetadata($id_event,'humidity',$matches[1]);
	    }


	    //Get Date/Location
	    $dateloc = $xpath->query("//*[@class='padbot5']");
	    
	    if ($dateloc->length > 0) {
		$parts=explode(",",$dateloc->item(0)->nodeValue);      
		$this->addEventMetadata($id_event,"location",$parts[0]);
		$this->addEventMetadata($id_event,"day",trim($parts[1]));
		$this->addEventMetadata($id_event,'date',date('Y-m-d',strtotime($parts[2] . " " . $parts[3])));
	    }

	    $tables = $dom->getElementsByTagName('table');
	    //Detect if there is data for this session. 
	    if(strlen($r)>800){
		//There's data, let's get it
		$rows = $tables->item(0)->getElementsByTagName('tr');
		if($rows->item(0)->getElementsByTagName('th')->item(1)->nodeValue=='Points'){
		    $format='RAC';
		}else{
		    $format='FP';
		}
		
		$rows->item(0)->parentNode->removeChild($rows->item(0));
		foreach ($rows as $row) {
		    echo ".";
		    $cols = $row->getElementsByTagName('td');
		    $res['id_race_categories_events']=$id_event;
		    if($format=='RAC'){
			$res['pos']=$cols->item(0)->nodeValue;
			$res['points']=$cols->item(1)->nodeValue;
			$res['rider_num']=$cols->item(2)->nodeValue;
			$res['rider_name']=$cols->item(3)->nodeValue;
			$res['rider_nation']=$cols->item(4)->nodeValue;
			$res['rider_team']=$cols->item(5)->nodeValue;	  	 
			$res['rider_bike']=$cols->item(6)->nodeValue;	  	  
			$res['speed']=$cols->item(7)->nodeValue;	  	  
			$res['time']=$cols->item(8)->nodeValue;	  	  
			$res['gap']=$cols->item(9)->nodeValue;	  
		    }else{
			$res['pos']=$cols->item(0)->nodeValue;
			$res['points']='';
			$res['rider_num']=$cols->item(1)->nodeValue;
			$res['rider_name']=$cols->item(2)->nodeValue;
			$res['rider_nation']=$cols->item(3)->nodeValue;
			$res['rider_team']=$cols->item(4)->nodeValue;	  	 
			$res['rider_bike']=$cols->item(5)->nodeValue;	  	  
			$res['speed']=$cols->item(6)->nodeValue;	  	  
			$res['time']=$cols->item(7)->nodeValue;	  	  
			$res['gap']=$cols->item(8)->nodeValue;	  	   
		    }
		    if($res['rider_name']){
			$db->prepare('
                                  INSERT INTO results SET
				      `id_race_categories_events`=:id_race_categories_events,
				      `pos`=:pos,
				      `points`=:points,
				      `rider_num`=:rider_num,
				      `rider_name`=:rider_name,
				      `rider_nation`=:rider_nation,
				      `rider_team`=:rider_team,
				      `rider_bike`=:rider_bike,
				      `speed`=:speed,
				      `time`=:time,
				      `gap`=:gap
				      ')->execute($res);
		    }
		}      
	    }else{
		//TODO: If there's No data, we should try the PDF. 
		echo "No data for this session";
	    }
	}
    }
}

?>
