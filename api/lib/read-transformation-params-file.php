<?php

/**
 * $marker is the key for the reference frames couple, eg: [ITRF89-ITRF00]
 * $is_inv is boolean
 * The output array is as follows:
 * ["ini_epoch"] => string
 * ["trasl_vect"] => array
 * ["rot_angles"] => array
 * ["scale_f"] => string
 * ["dot_trasl_vect"] => array
 * ["dot_rot_angles"] => array
 * ["dot_scale_f"] => string
 */
function readParams($filepath, $marker, $is_inv)
{
    //read file contents
    $lines = file($filepath);
    
    //cycle through lines
    foreach ($lines as $k => $line) {
        if(strpos($line, $marker) !== false){

            //Epoch
            $k++;
            $ini_epoch = substr($lines[$k], 7);

            //look for the first line after comments (aka: line after the "=" line)
            $found = false;
            do
            {
                $k++;
                if(ereg("^===",$lines[$k]) !== false)
                {
                    $key = $k;
                    $found = true;
                }
            }
            while(!$found);
            
            //Offset
            $offsetline = $lines[$key+3];            
            $tokens = split("[ \t]+", $offsetline); //split on whitespaces or tabs
            $trasl_vect[0] = $tokens[1];
            $trasl_vect[1] = $tokens[2];
            $trasl_vect[2] = $tokens[3];
            $rot_angles[0] = $tokens[4];
            $rot_angles[1] = $tokens[5];
            $rot_angles[2] = $tokens[6];
            $scale_f = $tokens[7];
            
            if($is_inv){
                $trasl_vect[0] *= -1;
                $trasl_vect[1] *= -1;
                $trasl_vect[2] *= -1;
                $rot_angles[0] *= -1;
                $rot_angles[1] *= -1;
                $rot_angles[2] *= -1;
                $scale_f *= -1;
            }

            //Drift
            $driftline = $lines[$key+7];
            $tks = split("[ \t]+", $driftline); //split on whitespaces or tabs
            $dot_trasl_vect[0] = $tks[1];
            $dot_trasl_vect[1] = $tks[2];
            $dot_trasl_vect[2] = $tks[3];
            $dot_rot_angles[0] = $tks[4];
            $dot_rot_angles[1] = $tks[5];
            $dot_rot_angles[2] = $tks[6];
            $dot_scale_f = $tks[7];
            
            if($is_inv){
                $dot_trasl_vect[0] *= -1;
                $dot_trasl_vect[1] *= -1;
                $dot_trasl_vect[2] *= -1;
                $dot_rot_angles[0] *= -1;
                $dot_rot_angles[1] *= -1;
                $dot_rot_angles[2] *= -1;
                $dot_scale_f *= -1;
            }

            break;
        }
    }
    
    return array("ini_epoch" => $ini_epoch, "trasl_vect" => $trasl_vect,
        "rot_angles" => $rot_angles, "scale_f" => $scale_f, 
        "dot_trasl_vect" => $dot_trasl_vect, "dot_rot_angles" => $dot_rot_angles,
        "dot_scale_f" => $dot_scale_f);
}
?>
