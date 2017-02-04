<?php

define("SEC_IN_WK", 604800.0); #number of seconds in a week
define("_ZEROWEEK", 44244); #the MJD corresponding to the 0 of the GPS time
define("_SEC_IN_A_DAY", 86400.0); #number of seconds in a day
define("SMALL", 1.0e-10); #a small number

function is_leap($year)
{
    $ret_val = 0;
    if(($year % 4 == 0 && $year % 100 != 0) || $year % 400 == 0)
    {
        $ret_val = 1;
    }
    return $ret_val;
}

function decimal_year($year, $doy, $num_doy)
{
    return $year + ($doy - 1.0)/$num_doy + 0.0002;
}

function gpstime_to_mjd($gps_week, $dow)
{ 
    return $gps_week * 7 + _ZEROWEEK + $dow;
}

function gpstime_to_doy($gps_week, $dow, &$doy, &$year)
{
    mjd_to_doy(gpstime_to_mjd($gps_week, $dow), $doy, $year);
    return 0;
}

function gpstime_to_date($gps_week, $dow, &$year, &$month, &$day)
{
    $mjd = gpstime_to_mjd($gps_week, $dow);
    mjd_to_date($mjd, $year, $month, $day);
    return 0;
}

function date_to_mjd($year, $month, $day)
{
    if($month<=2)
    {
        $iy = $year-1;
        $im = $month+12;
    }
    else
    {
        $iy = $year;
        $im = $month;
    }
    
    if($year>1582){
        $ib = $iy/400 - $iy/100;
    }
    else
    {
        $ib = -2;
        if($year == 1582)
        {
            if($month>10)
            {
                $ib = $iy/400 - $iy/100;
            }
            else if($month==10 && $day>=15)
            {
                $ib = $iy/400 - $iy/100;
            }
        }
   }
   
   $k1 = floor($iy * 365.25);
   $k2 = floor(($im + 1) * 30.6001);
   
   
   $result = $k1 + $k2 + $ib - 679004 + $day;
   return round($result);
   
   
   
}

function doy_to_gpstime($year, $doy, &$gps_week, &$dow)
{
    $l_mjd = doy_to_mjd($year, $doy) + SMALL;
    mjd_to_gpstime($l_mjd, $gps_week, $dow);
    return 0;
} 

function doy_to_mjd($year, $doy)
{
    return date_to_mjd($year, 1, 1) + $doy - 1.0;
}

function mjd_to_gpstime($l_mjd, &$gps_week, &$dow)
{
    $zero_mjd = $l_mjd - _ZEROWEEK; #now referred to ZEROWEEK
    $gps_week = floor($zero_mjd/7); #number of weeks completly passed
    $day_in_week = floor($zero_mjd - $gps_week*7); #number of days in this week
    $dow = $day_in_week;
    return 0;
}

function mjd_to_date($mjd, &$year, &$month, &$day)
{   
    $vect = explode('.', $mjd);
    $tmp = $vect[0];
    $l_mjd = floor($tmp + SMALL);
    

    $ia = $l_mjd + 2400001;

    if($ia<2299161)
    {
        $ic = $ia + 1524;
    }
    else
    {
        $ib = floor(($ia - 1867216.25)/36524.25);
        $ic = $ia + $ib - floor($ib/4.0) + 1525;
    }
    
    $id = floor(($ic - 122.1)/365.25);
    $ie = floor($id * 365.25);
    $if_ = floor(($ic - $ie)/30.6001);

    $day = floor($ic - $ie - floor($if_*30.6001));
    
    $v = floor($if_/14.0)*12;
    $mo = floor($if_ - 1 - $v);
 
    $month = $mo;
    
    $year = floor($id - 4715 - floor(($mo + 7)/10.0));
    
    return 0;
}

function mjd_to_doy($mjd, &$doy, &$year)
{    
    $day=0;
    $month=0;
    mjd_to_date($mjd, $year, $month, $day);
    $doy = floor($mjd - date_to_mjd($year, 1, 1) + SMALL) + 1;    
    return 0;
}

?>
