<?php

require_once 'GNSS_date_conversion_lib.php';

function determineformat($inputtext){
    
    //output array
    $result = array();
    $result["YYMMDD_output"] = '';
    $result["GPSTime_output"] = '';
    $result["mjd_output"] = '';
    $result["decimal_output"] = '';
    $result["year_doy_output"] = '';
    
    $max_year = 3000.0;
    $max_doy = 365.0;
    $min_mjd = 44244.0;
    $max_mjd = 100000.0;
    $max_gps_week = 2100.0;
    $max_dow = 6.0;

    $year_d = 0.0;
    $gps_week = 0;
    $dow = 0;
    $year = 0;
    $doy = 0;
    $month = 0;
    $day = 0;
    $mjd_int = 0.0;        
    $first_n = 0;
    $second_n = 0;

    $pippo = trim($inputtext);
    $lenght = strlen($pippo);

    //if input string is empty, return an empty array
    if($lenght == 0){
        return array();
    }

    $sp_pos = 0;
    $old_sp_pos = $sp_pos;
    $count_space = 0;

    #count spaces
    for ($i = 1; $i <= $lenght; $i++) {
        $sp_pos++;
        if (substr($pippo, $i-1, 1) == " "){
            $count_space = $count_space + 1;
            if ($first_n == 0){
                $first_n = $sp_pos;
                $old_sp_pos = $sp_pos - 1;
            }
            else if($second_n == 0){
                $second_n = $sp_pos;
                $old_sp_pos = $sp_pos - 1;
            }
            else if($count_space > 2){
                return array();
            }
        }
    }
    
    #case of decimal year and MJD
    if($count_space == 0 && is_numeric($pippo)){
        $d_num = floatval($pippo);
        if($d_num < 0.0){
            return array();
        }
        else if($d_num < $max_year){
            $year_d = $d_num;
            $result["decimal_output"] = strval($d_num);
        }
        else if ($d_num < $min_mjd || $d_num > $max_mjd){
            return array();
        }
        else{
            $mjd_int = sprintf('%05d', $d_num);
            $result["mjd_output"] = $mjd_int;
        }
    }
    #case of "YYYY-MM-DD" or "year and doy" or "GPS_week and dow"
    if($count_space == 2){
        if($first_n > 5){
            return array();
        }
        else{
            $year = substr($pippo, 0, $first_n-1);
            if(!is_numeric($year) || ($second_n - $first_n > 3)){
                return array();
            }
            $month = substr($pippo, $first_n, $second_n - $first_n - 1);
            if($lenght - $second_n > 2){
                return array();
            }
            $day = substr($pippo, $second_n, $lenght - $second_n);
            if(!controldate($year, $month, $day)){
                return array();
            }
            else{
                $result["YYMMDD_output"] = $pippo;
            }
        }
    }
    else if($count_space == 1){
        if($first_n > 5){
            return array();
        }
        else{
            $first_d = substr($pippo, 0, $first_n-1);
            if($lenght - $first_n > 3){
                return array();
            }
            else{
                $second_d = substr($pippo, $first_n, $lenght - $first_n);

                #determine the kind of data
                if($first_d < 0 || $second_d < 0){
                    return array();
                }
                else if($first_d > 1980 && $first_d < $max_year && ($lenght - $first_n > 1)){
                    $year = $first_d;
                    $doy = $second_d;
                    $y_leap = is_leap($year);
                    if($y_leap == 0 && $doy > $max_doy){
                        return array();
                    }
                    else if($y_leap == 1 && $doy > $max_doy + 1){
                        return array();
                    }
                    else{
                        $result["year_doy_output"] = $pippo;
                    }
                }
                else if ($first_d < $max_gps_week && ($lenght-$first_n == 1)){
                    $gps_week = $first_d;
                    $dow = $second_d;
                    if($dow > $max_dow){
                        return array();
                    }
                    else{
                        $result["GPSTime_output"] = $pippo;
                    }
                }
                else{
                    return array();
                }
            }
        }  
    }
    else if($count_space > 2){
        return array();
    }

    return buildoutput($year_d, $year, $doy, $mjd_int, $day, $month, $gps_week, $dow);
}

function controldate($yy, $mm, $dd){
    $ret_val = True;
    $max_year = 3000.0;
    $max_month = 12.0;
    $max_day = 31.0;

    if($yy < 0 || $yy > $max_year){
        $ret_val = False;
    }
    else if($mm < 0 || $mm > $max_month){
        $ret_val = False;
    }
    else if($dd < 0 || $dd > $max_day){
        $ret_val = False;
    }    
    return $ret_val;       
}

function buildoutput($year_double, $year, $doy, $mjd, $day, $month, $gps_week, $dow){
    $o_year = 0;
    $o_doy = 0;
    $o_month = 0;
    $o_day = 0;
    $o_gps_week = 0;
    $o_dow = 0;
    $o_mjd = 0.0;
    $o_dec_year = 0.0;
    
    if($year_double != 0.0){
        #get the decimal part and convert to doy
        #---> year and doy
        $o_dec_year = $year_double;
        $o_year = intval($year_double);
        if($o_year - $year_double > 0.0){
            $o_year = $o_year - 1;
        }
        $is_l = is_leap($o_year);
        if($is_l == 1){
            $num_doy = 366;
        }
        else if($is_l == 0){
            $num_doy = 365;
        }
        $frac = $year_double - $o_year;
        $d_doy = $frac*$num_doy;
        $n_doy = intval($d_doy) + 1;
        doy_to_gpstime($o_year, $n_doy, $o_gps_week, $o_dow);
        $n_year = 0;
        gpstime_to_date($o_gps_week, $o_dow, $n_year, $o_month, $o_day);
        $o_mjd = gpstime_to_mjd($o_gps_week, $o_dow);
        $o_doy = $n_doy;
    }
    else if($year != 0.0 && $doy != 0){
        $o_year = $year;
        $o_doy = $doy;
        $o_mjd = doy_to_mjd($year, $doy);
        mjd_to_gpstime($o_mjd, $o_gps_week, $o_dow);
        mjd_to_date($o_mjd, $year, $o_month, $o_day);
        $is_l = is_leap($o_year);
        if ($is_l == 1){
            $num_doy = 366;
        }
        else if ($is_l == 0){
            $num_doy = 365;
        }
        $o_dec_year = decimal_year($o_year, $o_doy, $num_doy);
    }
    else if($gps_week != 0){
        $o_gps_week = $gps_week;
        $o_dow = $dow;
        gpstime_to_date($gps_week, $dow, $o_year, $o_month, $o_day);
        $o_mjd = gpstime_to_mjd($gps_week, $dow);
        $n_year = $o_year;
        gpstime_to_doy($gps_week, $dow, $o_doy, $n_year);
        #transform doy in decimal part of the year
        $is_l = is_leap($o_year);
        if ($is_l == 1){
            $num_doy = 366;
        }
        else if ($is_l == 0){
            $num_doy = 365;
        }
        $o_dec_year = decimal_year($o_year, $o_doy, $num_doy);
    }
    else if ($mjd != 0){
        mjd_to_doy($mjd, $o_doy, $o_year);
        $n_year = $o_year;
        mjd_to_date($mjd, $n_year, $o_month, $o_day);
        mjd_to_gpstime($mjd, $o_gps_week, $o_dow);
        #transform doy in decimal part of the year
        $is_l = is_leap($o_year);
        if ($is_l == 1){
            $num_doy = 366;
        }
        else if ($is_l == 0){
            $num_doy = 365;
        }
        $o_mjd = $mjd;
        $o_dec_year = decimal_year($o_year, $o_doy, $num_doy);
    }
    else if ($year != 0 && $month != 0 && $day != 0){
        $o_year = $year;
        $o_month = $month;
        $o_day = $day;
        $o_mjd = date_to_mjd($year, $month, $day);
        $n_year = 0;
        mjd_to_doy($o_mjd, $o_doy, $n_year);  //here
        mjd_to_gpstime($o_mjd, $o_gps_week, $o_dow);
        #transform doy in decimal part of the year
        $is_l = is_leap($o_year);
        if ($is_l == 1){
            $num_doy = 366;
        }
        else if ($is_l == 0){
            $num_doy = 365;
        }
        $o_dec_year = decimal_year($o_year, $o_doy, $num_doy);
    }
    
    $result = array();
    $result["YYMMDD_output"] = sprintf('%04d',$o_year)." ".sprintf('%02d',$o_month)." ".sprintf('%02d',$o_day);
    $result["GPSTime_output"] = sprintf('%04d',$o_gps_week)." ".sprintf('%01d',$o_dow);
    $result["mjd_output"] = sprintf('%07.1lf',$o_mjd);
    $result["decimal_output"] = sprintf('%09.4lf',$o_dec_year);
    $result["year_doy_output"] = sprintf('%04d',$o_year)." ".sprintf('%03d',$o_doy);
    
    return $result;    
}

?>
