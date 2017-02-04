<?php

require_once 'lib/GNSS_date_conversion_handler.php';

//input GPS date patterns to be matched
$pattern_mjd = '^\d{5}$^';
$pattern_gpstime = '^\d{4}\s\d$^';
$pattern_yeardoy = '^\d{4}\s\d{3}$^';
$pattern_decimalyear_dot = '^\d{4}\.\d{1,4}$^';
$pattern_decimalyear_comma = '^\d{4}\,\d{1,4}$^';
$pattern_fulldate = '^\d{4}\s\d{2}\s\d{2}$^';

//set timezone to UTC
date_default_timezone_set('UTC');

//init result
$conversions = array();
$conversions["mjd_output"] = "";
$conversions["GPSTime_output"] = "";
$conversions["year_doy_output"] = "";
$conversions["decimal_output"] = "";
$conversions["YYMMDD_output"] = "";
$conversions["message"] = "";

//get POSTed date
$date = $_POST['date'];

# check format patterns: if input text does not match any of them, return error
if(preg_match($pattern_mjd, $date) == 0 && 
        preg_match($pattern_gpstime, $date) == 0 && 
        preg_match($pattern_yeardoy, $date) == 0 && 
        preg_match($pattern_decimalyear_dot, $date) == 0 && 
        preg_match($pattern_decimalyear_comma, $date) == 0 && 
        preg_match($pattern_fulldate, $date) == 0)
{
    $conversions["message"] = "Wrong input format";
}
else
{
    $date_r = str_replace(",",".", $date);  //replace eventual commas with dots
    $result = determineformat($date_r);
    if(empty($result))
    {
        $conversions["message"] = "Invalid date value";
    }
    else
    {
        $conversions["mjd_output"] = $result["mjd_output"];
        $conversions["GPSTime_output"] = $result["GPSTime_output"];
        $conversions["year_doy_output"] = $result["year_doy_output"];
        $conversions["decimal_output"] = $result["decimal_output"];
        $conversions["YYMMDD_output"] = $result["YYMMDD_output"];
        $conversions["message"] = "";
    }
}

//return JSON object
header('Content-Type: application/json');
print json_encode($conversions);
?>
