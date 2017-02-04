<?php

$rf = $_GET["reference_frame"];

//Reference frames start epochs lookup table
$lookuptable = array(
  "IGb00" => array("1998","001","0"),
  "IGb08" => array("2005","001","0"),
  "IGS97" => array("1997","001","0"),  
  "IGS00" => array("1998","001","0"),
  "IGS05" => array("2000","001","0"),  
  "IGS08" => array("2005","001","0"),
  "ITRF88" => array("1988","001","0"),  
  "ITRF89" => array("1988","001","0"),
  "ITRF90" => array("1988","001","0"),  
  "ITRF91" => array("1988","001","0"),
  "ITRF92" => array("1994","001","0"),  
  "ITRF93" => array("1995","001","0"),
  "ITRF94" => array("1996","001","0"),  
  "ITRF96" => array("1997","001","0"),
  "ITRF97" => array("1997","001","0"),
  "ITRF00" => array("1997","001","0"),
  "ITRF05" => array("2000","001","0")
);


if($rf == "" || $rf == null){
    header("HTTP/1.0 404 Not found");
    print "";
}
else{
    $value = $lookuptable[$rf];
    header('Content-Type: application/json');
    print json_encode(
            array("start_year" => $value[0], 
                "start_doy" => $value[1],
                "start_sod" => $value[2]
            ));
}

?>
