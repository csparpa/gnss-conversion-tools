<?php

include "read-transformation-params-file.php";

define("_PI", 3.141592653589793238); #pi
define("LONG_BUFF_LNG", 1024);
define("BUFF_LNG", 128);
define("SMALL", 1.0e-10); #a small number
define("INV_FLAT", 298.257223563); #WGS84 conventional inverse flatness of the Earth
define("EARTH_RAD", 6378137.0);  #WGS84 Earth radius in meters (ellipsoid semimajor axis)

function mng_coordinates_transformation(&$path, &$st_ref,&$end_ref, $st_x_coord, 
        $st_y_coord, $st_z_coord, $st_x_vel, $st_y_vel, $st_z_vel, $start_year, 
        $start_doy, $start_sod, $end_year, $end_doy, $end_sod, &$end_x_coord,
        &$end_y_coord, &$end_z_coord)
{
    //1st. propagate start coordinates from start epoch to end epoch
    $vel = array($st_x_vel, $st_y_vel,$st_z_vel);
    $coord_var = array(0.0, 0.0, 0.0);
    propagate_coordinates($vel, $start_year, $start_doy, $start_sod, $end_year, 
            $end_doy, $end_sod, $coord_var);

    $propag_coord = array($st_x_coord + $coord_var[0],$st_y_coord + $coord_var[1],
        $st_z_coord + $coord_var[2]);
   
    //2nd. transform from 'st_ref' to 'end_ref' at the same end epoch
    $end_epoch_year = $end_year + ($end_doy - 1.0)/365.25 + $end_sod/(86400.0 * 365.25);
    $end_coord = array(0.0, 0.0, 0.0); 
    reference_frames_alignment($path, $st_ref, $propag_coord, $end_epoch_year, $end_ref, $end_coord);
    
    $end_x_coord = $end_coord[0];
    $end_y_coord = $end_coord[1];
    $end_z_coord = $end_coord[2];
    
    return 0;
}

function propagate_coordinates(&$vel, $start_year, $start_doy, $start_sod,
                           $current_year, $current_doy, $current_sod, &$coo_var)
{
   $st_year = $start_year + ($start_doy - 1.0)/365.25 + $start_sod/(365.25 * 86400.0);
   $end_year = $current_year + ($current_doy - 1.0)/365.25 + $current_sod/(365.25 * 86400.0);

   $coo_var[0] = linear_evol(0.0, $vel[0], $end_year, $st_year);
   $coo_var[1] = linear_evol(0.0, $vel[1], $end_year, $st_year);
   $coo_var[2] = linear_evol(0.0, $vel[2], $end_year, $st_year);
}

function reference_frames_alignment(&$path, &$from_ref, &$from_coord, 
        $curr_year_ep, &$to_ref, &$to_coord)
{
   $n_steps = 0;
   $count = 0;
   $trans_buff = "";
   
   //this function contains all the implemented transformation. See 'mod_manager.h'
   $inv_transf = mng_trf_transf($from_ref, $to_ref, $n_steps, $trans_buff);
   
   if($n_steps > 0)
   {
      $intmd_coord = array();
      $st_coord = array();

      for($l = 0; $l < 3; $l++)
      {
         $st_coord[$l] = $from_coord[$l];
      }

      $start_ep = $curr_year_ep;

      do
      {//in case of not alligned trf
         $datum = "";
         ur_data($trans_buff, 13*$count, 13, $datum);

         $trf_key = "[".$datum."]";
         
         $is_inv = $inv_transf[$count];
         
         $angl = array();
         $t_vect = array();
         $scale_f = 0.0;
         $d_scale_f = 0.0;
         $ref_ep = 0.0;
         $d_angl = array();
         $d_t_vect = array();
       
         //it raises exceptions 0x402 and 0x403
         read_trf_transf_param($path, $trf_key, $is_inv, $angl, $t_vect, 
                 $scale_f, $ref_ep, $d_angl, $d_t_vect, $d_scale_f);
         
         trf_evolve_and_transform($st_coord, $start_ep, $ref_ep, $angl, $d_angl, 
                 $t_vect, $d_t_vect, $scale_f, $d_scale_f, $intmd_coord);
          

         if($n_steps > 1)
         {
            for($l = 0; $l < 3; $l++)
            {
               $st_coord[$l] = $intmd_coord[$l];
            }
         }
         $count++;
      }
      while($count < $n_steps);

      for($l = 0; $l < 3; $l++)
      {
         $to_coord[$l] = $intmd_coord[$l];
      }
   }
   else if($n_steps == 0)
   {//in case of alligned trf
      for($l = 0; $l < 3; $l++)
      {
         $to_coord[$l] = $from_coord[$l];
      }
   }
   else if($n_steps == -1)
   {
      if($inv_transf != null)
      {
         unset($inv_transf);   //????
      }
      return - 1;
   }
   
   if($inv_transf != null)
   {
      unset($inv_transf);
   }
   return 0;
}


function trf_evolve_and_transform(&$curr_coord, $curr_ep, $ref_ep, &$angl, &$d_angl,
        &$t_vect, &$d_t_vect, $scale_f, $d_scale_f, &$out_coord)
{
   //evolve parameters
   $angl[0] = linear_evol($angl[0], $d_angl[0], $curr_ep, $ref_ep);
   $angl[1] = linear_evol($angl[1], $d_angl[1], $curr_ep, $ref_ep);
   $angl[2] = linear_evol($angl[2], $d_angl[2], $curr_ep, $ref_ep);

   $t_vect[0] = linear_evol($t_vect[0], $d_t_vect[0], $curr_ep, $ref_ep);
   $t_vect[1] = linear_evol($t_vect[1], $d_t_vect[1], $curr_ep, $ref_ep);
   $t_vect[2] = linear_evol($t_vect[2], $d_t_vect[2], $curr_ep, $ref_ep);

   $scale_f = linear_evol($scale_f, $d_scale_f, $curr_ep, $ref_ep);

   //transform between the two TRF
   ref_frame_transf($curr_coord, $angl, $t_vect, $scale_f, $out_coord);
}


function ref_frame_transf(&$coord_A, &$rot_angles, &$t_vect, $k_param, &$coord_B)
{
   //rotation angle 'rot_angles' are supplied in (mas) unit
   //rotation angle are transformed in radiants
   for($i = 0; $i < 3; $i++)
      $rot_angles[$i] *= _PI / 180.0 * 1.0 / (3600.0 * 1000.0);

   //scale factor 'k_param' is supplied in (ppb) unit
   //scale factor is transformed in units
   $k_param *= 1.0e-9;

   //traslation vector 't_vect' is supplied in (mm) unit
   //translation vector are transformed in meters
   for($i = 0; $i < 3; $i++)
      $t_vect[$i] *= 1.0e-3;

   seven_parameters_transf($coord_A, $rot_angles, $t_vect, $k_param, $coord_B); 
}

function seven_parameters_transf(&$start_point, &$rot_angles, &$trasl_vect, 
        $scale_fact, &$end_point)
{
   $r_x = $rot_angles[0];
   $r_y = $rot_angles[1];
   $r_z = $rot_angles[2];
   $k = $scale_fact;

   $end_point[0] = (1.0 + $k)*$start_point[0]-$r_z*$start_point[1]+$r_y*$start_point[2];
   $end_point[1] = $r_z*$start_point[0]+(1.0 + $k)*$start_point[1]-$r_x*$start_point[2];
   $end_point[2] = -$r_y*$start_point[0]+$r_x*$start_point[1]+(1.0 + $k)*$start_point[2];

   for($i = 0; $i < 3; $i++)
      $end_point[$i] += $trasl_vect[$i];
}

function linear_evol($y_0, $dot_y, $x, $x_0)
{
   return $y_0 + $dot_y*($x - $x_0);

}


function ur_data(&$record, $start, $len, &$datum)
{
    if(strlen($record) <= $start || $len < 1){
        $datum = "";
    }
    else
    {
        $datum = substr($record, $start, $len);
    }
}

function read_trf_transf_param(&$path, &$key, $is_inv, &$rot_angles, &$trasl_vect, 
        &$scale_f, &$ini_epoch, &$dot_rot_angles, &$dot_trasl_vect, &$dot_scale_f)
{
    $file_name = $path;
    $arr = readParams($file_name, $key, $is_inv);
    $rot_angles = $arr["rot_angles"];
    $trasl_vect = $arr["trasl_vect"];
    $scale_f = $arr["scale_f"];
    $ini_epoch = $arr["ini_epoch"];
    $dot_rot_angles = $arr["dot_rot_angles"];
    $dot_trasl_vect = $arr["dot_trasl_vect"];
    $dot_scale_f = $arr["dot_scale_f"];
}

function mng_trf_transf(&$from_ref, &$to_ref, &$n_step, &$trf_buff)
{
   $n_intmd = -1;
   $datum = "";

   if($from_ref == $to_ref)
   {
      $n_intmd = 0;
      $n_step = $n_intmd;
      $vect = null;
      $trf_buff = null;
      return $vect;
   }

   if($from_ref == "ITRF00")
   {
      if($to_ref == "ITRF05")
      {
          $n_intmd = 1;
         $trf_buff = "ITRF00-ITRF05";
         $vect = array(true);
      }
      else if($to_ref == "IGS97")
      {
          $n_intmd = 1;
         $trf_buff = "IGS00 - IGS97";
         $vect = array(true);
      }
      else if($to_ref == "IGb00")
      {
          $n_intmd = 0;
         $trf_buff = "";
         $vect = null;
      }
      else if($to_ref == "IGS00")
      {
          $n_intmd = 0;
         $trf_buff = "";
         $vect = null;
      }
      else if($to_ref == "IGS08")
      {
          $n_intmd = 2;
         $trf_buff = "IGb00 - IGS05IGS08 - IGS05";
         $vect = array(true,false);
      }
      else if($to_ref == "IGS05")
      {
          $n_intmd = 1;
         $trf_buff = "IGb00 - IGS05";
         $vect = array(true);
      }
      else if($to_ref == "ETRS89")
      {
          $n_intmd = 1;
         $trf_buff = "ETRS89-ITRF00";
         $vect = array(false);
      }
      else if(strpos($to_ref, "ITRF") === 0)
      {
          $n_intmd = 1;
         ur_data($to_ref,4,2, $datum);
         $trf_buff = "ITRF".$datum."-ITRF00";
         $vect = array(false);
      }
      else if($to_ref == "IGM95")
      {
          $n_intmd = 1;
         $trf_buff = "IGM95 - IGb00";
         $vect = array(false);
      }
      else if($to_ref == "IGb08")
      {
          $n_intmd = 3;
         $trf_buff = "IGb00 - IGS05IGS08 - IGS05IGb08 - IGS08";
         $vect = array(true, false, false);
      }
      else
      {//reference frame not managed
         $trf_buff = "";
         $n_intmd = -1;
         $vect = null;  
      }
   }
   else if($from_ref == "ITRF05")
   {
      if($to_ref == "ITRF00")
      {
          $n_intmd = 1;
         $trf_buff = "ITRF00-ITRF05";
         $vect = array(false);
      }
      else if($to_ref == "IGS97")
      {
          $n_intmd = 4;
         $trf_buff = "ITRF05- IGS05IGb00 - IGS05IGS00 - IGb00IGS00 - IGS97";
         $vect = array(true, false, false, true);
      }
      else if($to_ref == "IGb00")
      {
          $n_intmd = 1;
         $trf_buff = "ITRF00-ITRF05";
         $vect = array(false);
      }
      else if($to_ref == "IGS00")
      {
          $n_intmd = 1;
         $trf_buff = "ITRF00-ITRF05";
         $vect = array(false);

      }
      else if($to_ref == "IGS05")
      {
          $n_intmd = 1;
         $trf_buff = "ITRF05- IGS05";
         $vect = array(true);
      }
      else if($to_ref == "IGS08")
      {
          $n_intmd = 2;
         $trf_buff = "ITRF05- IGS05IGS08 - IGS05";
         $vect = array(true, false);
      }
      else if($to_ref == "ETRS89")
      {
          $n_intmd = 1;
         $trf_buff = "ETRS89-ITRF05";
         $vect = array(false);
      }
      else if(strpos($to_ref, "ITRF") === 0)
      {
          $n_intmd = 2;
         ur_data($to_ref,4,2, $datum);
         $trf_buff = "ITRF00-ITRF05ITRF".$datum."-ITRF00";
         $vect = array(false, false);
      }
      else if($to_ref == "IGM95")
      {
          $n_intmd = 2;
         $trf_buff = "ITRF00-ITRF05IGM95 - IGb00";
         $vect = array(false, false);
      }
      else if($to_ref == "IGb08")
      {
          $n_intmd = 3;
         $trf_buff = "ITRF05- IGS05IGS08 - IGS05IGb08 - IGS08";
         $vect = array(true, false, false);
      }
      else
      {//reference frame not managed
         $trf_buff = "";
         $n_intmd = -1;
         $vect = null;
      }
   }
   else if($from_ref == "IGS97")
   {
      if($to_ref == "ITRF00")
      {
          $n_intmd = 1;
         $trf_buff = "IGS00 - IGS97";
         $vect = array(false);
      }
      else if($to_ref == "ITRF05")
      {
          $n_intmd = 2;
         $trf_buff = "IGS00 - IGS97ITRF00-ITRF05";
         $vect = array(false, true);
      }
      else if($to_ref == "IGb00")
      {
          $n_intmd = 2;
         $trf_buff = "IGS00 - IGS97IGS00 - IGb00";
         $vect = array(false, true);
      }
      else if($to_ref == "IGS00")
      {
          $n_intmd = 1;
         $trf_buff = "IGS00 - IGS97";
         $vect = array(false);
      }
      else if($to_ref == "IGS08")
      {
          $n_intmd = 4;
         $trf_buff = "IGS00 - IGS97IGS00 - IGb00IGb00 - IGS05IGS08 - IGS05";
         $vect = array(false, true, true, false);
      }
      else if($to_ref == "IGS05")
      {
          $n_intmd = 3;
         $trf_buff = "IGS00 - IGS97IGS00 - IGb00IGb00 - IGS05";
         $vect = array(false, true, true);
      }
      else if($to_ref == "ETRS89")
      {
          $n_intmd = 1;
         $trf_buff = "ETRS89-ITRF97";
         $vect = array(false);
      }
      else if(strpos($to_ref, "ITRF") === 0)
      {
          $n_intmd = 2;
         ur_data($to_ref,4,2, $datum);
         $trf_buff = "IGS00 - IGS97ITRF".$datum."-ITRF00";
         $vect = array(false, false);
      }
      else if($to_ref == "IGM95")
      {
          $n_intmd = 3;
         $trf_buff = "IGS00 - IGS97IGS00 - IGb00IGM95 - IGb00";
         $vect = array(false, true, false);
      }
      else if($to_ref == "IGb08")
      {
          $n_intmd = 5;
         $trf_buff = "IGS00 - IGS97IGS00 - IGb00IGb00 - IGS05IGS08 - IGS05IGb08 - IGS08";
         $vect = array(false, true, true, false, false);
      }
      else
      {//reference frame not managed
         $trf_buff = "";
         $n_intmd = -1;
         $vect = null;  
      }
   }
   else if($from_ref == "IGb00")
   {
      if($to_ref == "ITRF00")
      {
          $n_intmd = 0;
         $trf_buff = "";
         $vect = null;
      }
      else if($to_ref == "ITRF05")
      {
          $n_intmd = 1;
         $trf_buff = "ITRF00-ITRF05";
         $vect = array(true);
      }
      else if($to_ref == "IGS97")
      {
          $n_intmd = 2;
         $trf_buff = "IGS00 - IGb00IGS00 - IGS97";
         $vect = array(false, true);
      }
      else if($to_ref == "IGS00")
      {
          $n_intmd = 1;
         $trf_buff = "IGS00 - IGb00";
         $vect = array(false);
      }
      else if($to_ref == "IGS05")
      {
          $n_intmd = 1;
         $trf_buff = "IGb00 - IGS05";
         $vect = array(true);
      }
      else if($to_ref == "IGS08")
      {
          $n_intmd = 2;
         $trf_buff = "IGb00 - IGS05IGS08 - IGS05";
         $vect = array(true, false);
      }
      else if($to_ref == "ETRS89")
      {
          $n_intmd = 1;
         $trf_buff = "ETRS89-ITRF00";
         $vect = array(false);
      }
      else if(strpos($to_ref, "ITRF") === 0)
      {
          $n_intmd = 1;
         ur_data($to_ref,4,2, $datum);
         $trf_buff = "ITRF".$datum."-ITRF00";
         $vect = array(false);
      }
      else if($to_ref == "IGM95")
      {
          $n_intmd = 1;
         $trf_buff = "IGM95 - IGb00";
         $vect = array(false);
      }
      else if($to_ref == "IGb08")
      {
          $n_intmd = 3;
         $trf_buff = "IGb00 - IGS05IGS08 - IGS05IGb08 - IGS08";
         $vect = array(true, false, false);
      }
      else
      {//reference frame not managed
         $trf_buff = "";
         $n_intmd = -1;
         $vect = null;
      }
   }
   else if($from_ref == "IGS00")
   {
      if($to_ref == "ITRF00")
      {
          $n_intmd = 0;
         $trf_buff = "";
         $vect = null;
      }
      else if($to_ref == "ITRF05")
      {
          $n_intmd = 1;
         $trf_buff = "ITRF00-ITRF05";
         $vect = array(true);
      }
      else if($to_ref == "IGS97")
      {
          $n_intmd = 1;
         $trf_buff = "IGS00 - IGS97";
         $vect = array(true);
      }
      else if($to_ref == "IGb00")
      {
          $n_intmd = 1;
         $trf_buff = "IGS00 - IGb00";
         $vect = array(true);
      }
      else if($to_ref == "IGS05")
      {
          $n_intmd = 2;
         $trf_buff = "IGS00 - IGb00IGb00 - IGS05";
         $vect = array(true, true);
      }
      else if($to_ref == "IGS08")
      {
          $n_intmd = 3;
         $trf_buff = "IGS00 - IGb00IGb00 - IGS05IGS08 - IGS05";
         $vect = array(true, true, false);
      }
      else if($to_ref == "ETRS89")
      {
          $n_intmd = 1;
         $trf_buff = "ETRS89-ITRF00";
         $vect = array(false);
      }
      else if(strpos($to_ref, "ITRF") === 0)
      {
          $n_intmd = 1;
         ur_data($to_ref,4,2, $datum);
         $trf_buff = "ITRF".$datum."-ITRF00";
         $vect = array(false);
      }
      else if($to_ref == "IGM95")
      {
          $n_intmd = 2;
         $trf_buff = "IGS00 - IGb00IGM95 - IGb00";
         $vect = array(true, false);
      }
      else if($to_ref == "IGb08")
      {
          $n_intmd = 4;
         $trf_buff = "IGS00 - IGb00IGb00 - IGS05IGS08 - IGS05IGb08 - IGS08";
         $vect = array(true, true, false, false);
      }
      else
      {//reference frame not managed
         $trf_buff = "";
         $n_intmd = -1;
         $vect = null;
      }
   }
   else if($from_ref == "IGS05")
   {
      if($to_ref == "ITRF00")
      {
          $n_intmd = 1;
         $trf_buff = "IGb00 - IGS05";
         $vect = array(false);
      }
      else if($to_ref == "ITRF05")
      {
          $n_intmd = 1;
         $trf_buff = "ITRF05- IGS05";
         $vect = array(false);
      }
      else if($to_ref == "IGS97")
      {
          $n_intmd = 3;
         $trf_buff = "IGb00 - IGS05IGS00 - IGb00IGS00 - IGS97";
         $vect = array(false, false, true);
      }
      else if($to_ref == "IGb00")
      {
          $n_intmd = 1;
         $trf_buff = "IGb00 - IGS05";
         $vect = array(false);
      }
      else if($to_ref == "IGS08")
      {
          $n_intmd = 1;
         $trf_buff = "IGS08 - IGS05";
         $vect = array(false);
      }
      else if($to_ref == "IGS00")
      {
          $n_intmd = 2;
         $trf_buff = "IGb00 - IGS05IGS00 - IGb00";
         $vect = array(false, false);
      }
      else if($to_ref == "ETRS89")
      {
          $n_intmd = 2;
         $trf_buff = "ITRF05- IGS05ETRS89-ITRF05";
         $vect = array(false, false);
      }
      else if(strpos($to_ref, "ITRF") === 0)
      {                  
          $n_intmd = 3;
         ur_data($to_ref,4,2, $datum);
         $trf_buff = "ITRF05- IGS05ITRF00-ITRF05ITRF".$datum."-ITRF00";
         $vect = array(false, false, false);
      }
      else if($to_ref == "IGM95")
      {
          $n_intmd = 2;
         $trf_buff = "IGb00 - IGS05IGM95 - IGb00";
         $vect = array(false, false);
      }
      else if($to_ref == "IGb08")
      {
          $n_intmd = 2;
         $trf_buff = "IGS08 - IGS05IGb08 - IGS08";
         $vect = array(false, false);
      }
      else
      {//reference frame not managed
         $n_intmd = -1;
         $trf_buff = "";
         $vect = null;
      }
   } 
   else if($from_ref == "IGS08")
   {
      if($to_ref == "ITRF00")
      {
          $n_intmd = 2;
         $trf_buff = "IGS08 - IGS05IGb00 - IGS05";
         $vect = array(true, false);
      }
      else if($to_ref == "ITRF05")
      {
          $n_intmd = 2;
         $trf_buff = "IGS08 - IGS05ITRF05- IGS05";
         $vect = array(true, false);
      }
      else if($to_ref == "IGS97")
      {
          $n_intmd = 4;
         $trf_buff = "IGS08 - IGS05IGb00 - IGS05IGS00 - IGb00IGS00 - IGS97";
         $vect = array(true, false, false, true);
      }
      else if($to_ref == "IGb00")
      {
          $n_intmd = 2;
         $trf_buff = "IGS08 - IGS05IGb00 - IGS05";
         $vect = array(true, false);
      }
      else if($to_ref == "IGS00")
      {
          $n_intmd = 3;
         $trf_buff = "IGS08 - IGS05IGb00 - IGS05IGS00 - IGb00";
         $vect = array(true, false, false);
      }
      else if($to_ref == "ETRS89")
      {
          $n_intmd = 3;
         $trf_buff = "IGS08 - IGS05ITRF05- IGS05ETRS89-ITRF05";
         $vect = array(true, false, false);
      }
      else if(strpos($to_ref, "ITRF") === 0)
      {
          $n_intmd = 4;
         ur_data($to_ref,4,2, $datum);
         $trf_buff = "IGS08 - IGS05ITRF05- IGS05ITRF00-ITRF05ITRF".$datum."-ITRF00";
         $vect = array(true, false, false, false);
      }
      else if($to_ref == "IGM95")
      {
          $n_intmd = 3;
         $trf_buff = "IGS08 - IGS05IGb00 - IGS05IGM95 - IGb00";
         $vect = array(true, false, false);
      }
      else if($to_ref == "IGS05")
      {
          $n_intmd = 1;
         $trf_buff = "IGS08 - IGS05";
         $vect = array(true);
      }
      else if($to_ref == "IGb08")
      {
          $n_intmd = 1;
         $trf_buff = "IGb08 - IGS08";
         $vect = array(false);
      }
      else
      {//reference frame not managed
         $trf_buff = "";
         $n_intmd = -1;
         $vect = null;
      }
   }
   else if($from_ref == "ETRS89")
   {
      if($to_ref == "ITRF00")
      {
          $n_intmd = 1;
         $trf_buff = "ETRS89-ITRF00";
         $vect = array(true);
      }
      else if($to_ref == "ITRF05")
      {
          $n_intmd = 1;
         $trf_buff = "ETRS89-ITRF05";
         $vect = array(true);
      }
      else if($to_ref == "IGS97")
      {
          $n_intmd = 1;
         $trf_buff = "ETRS89-ITRF97";
         $vect = array(true);
      }
      else if($to_ref == "IGb00")
      {
          $n_intmd = 1;
         $trf_buff = "ETRS89-ITRF00";
         $vect = array(true);
      }
      else if($to_ref == "IGS00")
      {
          $n_intmd = 1;
         $trf_buff = "ETRS89-ITRF00";
         $vect = array(true);
      }
      else if($to_ref == "IGS05")
      {
          $n_intmd = 2;
         $trf_buff = "ETRS89-ITRF05ITRF05- IGS05";
         $vect = array(true, true);
      }
      else if($to_ref == "IGS08")
      {
          $n_intmd = 3;
         $trf_buff = "ETRS89-ITRF05ITRF05- IGS05IGS08 - IGS05";
         $vect = array(true, true, false);
      }
      else if(strpos($to_ref, "ITRF") === 0)
      {
         ur_data($to_ref,4,2, $datum);
         if($datum == "97")
         {
             $n_intmd = 1;
            $trf_buff = "ETRS89-ITRF".$datum;
            $vect = array(false);
         }
         else
         {
             $n_intmd = 2;
            $trf_buff = "ETRS89-ITRF00ITRF".$datum."-ITRF00";
            $vect = array(true, false);
         }
      }
      else if($to_ref == "IGM95")
      {
          $n_intmd = 2;
         $trf_buff = "ETRS89-ITRF00IGM95 - IGb00";
         $vect = array(true, false);
      }
      else if($to_ref == "IGb08")
      {
          $n_intmd = 4;
         $trf_buff = "ETRS89-ITRF05ITRF05- IGS05IGS08 - IGS05IGb08 - IGS08";
         $vect = array(true, true, false, false);
      }
      else
      {//reference frame not managed
         $trf_buff = "";
         $n_intmd = -1;
         $vect = null;
      }
   }
   else if(strpos($from_ref, "ITRF") === 0)
   {      
      ur_data($from_ref,4,2, $datum);
       
      if($to_ref == "ITRF00")
      {
          $n_intmd = 1;
         $trf_buff = "ITRF".$datum."-ITRF00";
         $vect = array(true);
      }
      else if($to_ref == "ITRF05")
      {
          $n_intmd = 2;
         $trf_buff = "ITRF".$datum."-ITRF00ITRF00-ITRF05";
         $vect = array(true, true);
      }
      else if(strpos($to_ref, "ITRF") === 0)
      {
         $n_intmd = 2;
         $target = "";
         ur_data($to_ref,4,2, $target);
         $trf_buff = "ITRF".$datum."-ITRF00ITRF".$target."-ITRF00";
         $vect = array(true, false);
      }
      else if($to_ref == "IGS97")
      {
         if($datum == "97")
         {
             $n_intmd = 0;
            $trf_buff = "";
            $vect = null;
         }
         else
         {
             $n_intmd = 2;
            $trf_buff = "ITRF".$datum."-ITRF00IGS00 - IGS97";
            $vect = array(true, true);
         }
      }
      else if($to_ref == "IGb00")
      {
          $n_intmd = 1;
         $trf_buff = "ITRF".$datum."-ITRF00";
         $vect = array(true);
      }
      else if($to_ref == "IGS00")
      {
          $n_intmd = 1;
         $trf_buff = "ITRF".$datum."-ITRF00";
         $vect = array(true);
      }
      else if($to_ref == "IGS05")
      {
          $n_intmd = 2;
         $trf_buff = "ITRF".$datum."-ITRF00IGb00 - IGS05";
         $vect = array(true, true);
      }
      else if($to_ref == "IGS08")
      {
          $n_intmd = 3;
         $trf_buff = "ITRF".$datum."-ITRF00IGb00 - IGS05IGS08 - IGS05";
         $vect = array(true, true, false);
      }
      else if($to_ref == "ETRS89")
      {
         if($datum == "97")
         {
             $n_intmd = 1;
            $trf_buff = "ETRS89-ITRF".$datum;
            $vect = array(false);
         }
         else
         {
             $n_intmd = 2;
            $trf_buff = "ITRF".$datum."-ITRF00ETRS89-ITRF00";
            $vect = array(true, false);
         }
      }
      else if($to_ref == "IGM95")
      {
          $n_intmd = 2;
         $trf_buff = "ITRF".$datum."-ITRF00IGM95 - IGb00";
         $vect = array(true, false);
      }
      else if($to_ref == "IGb08")
      {
          $n_intmd = 4;
         $trf_buff = "ITRF".$datum."-ITRF00IGb00 - IGS05IGS08 - IGS05IGb08 - IGS08";
         $vect = array(true, true, false, false);
      }
      else
      {//reference frame not managed
         $trf_buff = "";
         $n_intmd = -1;
         $vect = null;
      }
   }
   else if($from_ref == "IGM95")
   {
      if($to_ref == "ITRF00")
      {
          $n_intmd = 1;
         $trf_buff = "IGM95 - IGb00";
         $vect = array(true);
      }
      else if($to_ref == "ITRF05")
      {
          $n_intmd = 2;
         $trf_buff = "IGM95 - IGb00ITRF00-ITRF05";
         $vect = array(true, true);
      }
      else if($to_ref == "IGS97")
      {
          $n_intmd = 3;
         $trf_buff = "IGM95 - IGb00IGS00 - IGb00IGS00 - IGS97";
         $vect = array(true, false, true);
      }
      else if($to_ref == "IGb00")
      {
          $n_intmd = 1;
         $trf_buff = "IGM95 - IGb00";
         $vect = array(true);
      }
      else if($to_ref == "IGS00")
      {
          $n_intmd = 2;
         $trf_buff = "IGM95 - IGb00IGS00 - IGb00";
         $vect = array(true, false);
      }
      else if($to_ref == "IGS05")
      {
          $n_intmd = 2;
         $trf_buff = "IGM95 - IGb00IGb00 - IGS05";
         $vect = array(true, true);
      }
      else if($to_ref == "IGS08")
      {
          $n_intmd = 3;
         $trf_buff = "IGM95 - IGb00IGb00 - IGS05IGS08 - IGS05";
         $vect = array(true, true, false);
      }
      else if(strpos($to_ref, "ITRF") === 0)
      {
          $n_intmd = 2;
         ur_data($to_ref,4,2, $datum);
         $trf_buff = "IGM95 - IGb00ITRF".$datum."-ITRF00";
         $vect = array(true, false);
      }
      else if($to_ref == "ETRS89")
      {
          $n_intmd = 2;
         $trf_buff = "IGM95 - IGb00ETRS89-ITRF00";
         $vect = array(true, false);
      }
      else if($to_ref == "IGb08")
      {
          $n_intmd = 4;
         $trf_buff = "IGM95 - IGb00IGb00 - IGS05IGS08 - IGS05IGb08 - IGS08";
         $vect = array(true, true, false, false);
      }
      else
      {//reference frame not managed
         $trf_buff = "";
         $n_intmd = -1;
         $vect = null;
      }
   }
   else if($from_ref == "IGb08")
   {
      if($to_ref == "ITRF00")
      {
          $n_intmd = 3;
         $trf_buff = "IGb08 - IGS08IGS08 - IGS05IGb00 - IGS05";
         $vect = array(true, true, false);
      }
      else if($to_ref == "ITRF05")
      {
          $n_intmd = 3;
         $trf_buff = "IGb08 - IGS08IGS08 - IGS05ITRF05- IGS05";
         $vect = array(true, true, false);
      }
      else if($to_ref == "IGS97")
      {
          $n_intmd = 5;
         $trf_buff = "IGb08 - IGS08IGS08 - IGS05IGb00 - IGS05IGS00 - IGb00IGS00 - IGS97";
         $vect = array(true, true, false, false, true);
      }
      else if($to_ref == "IGb00")
      {
          $n_intmd = 3;
         $trf_buff = "IGb08 - IGS08IGS08 - IGS05IGb00 - IGS05";
         $vect = array(true, true, false);
      }
      else if($to_ref == "IGS00")
      {
          $n_intmd = 4;
         $trf_buff = "IGb08 - IGS08IGS08 - IGS05IGb00 - IGS05IGS00 - IGb00";
         $vect = array(true, true, false, false);
      }
      else if($to_ref == "ETRS89")
      {
          $n_intmd = 4;
         $trf_buff = "IGb08 - IGS08IGS08 - IGS05ITRF05- IGS05ETRS89-ITRF05";
         $vect = array(true, true, false, false);
      }
      else if(strpos($to_ref, "ITRF") === 0)
      {
          $n_intmd = 5;
         ur_data($to_ref,4,2, $datum);
         $trf_buff = "IGb08 - IGS08IGS08 - IGS05ITRF05- IGS05ITRF00-ITRF05ITRF".$datum."-ITRF00";
         $vect = array(true, true, false, false, false);
      }
      else if($to_ref == "IGM95")
      {
          $n_intmd = 4;
         $trf_buff = "IGb08 - IGS08IGS08 - IGS05IGb00 - IGS05IGM95 - IGb00";
         $vect = array(true, true, false, false);
      }
      else if($to_ref == "IGS05")
      {
          $n_intmd = 2;
         $trf_buff = "IGb08 - IGS08IGS08 - IGS05";
         $vect = array(true, true);
      }
      else if($to_ref == "IGS08")
      {
          $n_intmd = 1;
         $trf_buff = "IGb08 - IGS08";
         $vect = array(true);
      }
      else
      {//reference frame not managed
         $trf_buff = "";
         $n_intmd = -1;
         $vect = null;
      }
   }
   else
   {//reference frame not managed
      $trf_buff = "";
      $n_intmd = -1;
      $vect = null;
   }

   $n_step = $n_intmd;
   return $vect;
}

function str_to_lng($str_num)
{
   if(is_null($str_num) || strlen($str_num) < 1)
   {
      return 0;
   }
   $dbl_num = str_to_dbl($str_num);
   if($dbl_num < 0.0){
      $rval = round_number($dbl_num - SMALL);
   }
   else{
      $rval = round_number($dbl_num + SMALL);
   }
   if(abs($rval) > LONG_MAX){
      return 0;
   }
   return $rval;
}

function is_leap($year)
{
   $ret_val = false;
   if(($year % 4 == 0 && $year % 100 != 0) || $year % 400 == 0)
   {
      $ret_val = true;
   }
   return $ret_val;
}

function round_number($num)
{
   $sign = 1.0;
   
   if($num < 0.0)
   {
      $sign = -1.0;
      $num = abs($num);
   }

   if($num < 0.5)
      return 0.0;
   
   $frac = $num - $num - 1.0e-10;
   if($frac > 0.5){
        $rval = $num - $frac + 1; 
   }
   else{
        $rval = $num - $frac;   
   }
   return $sign*$rval;
}

function str_to_dbl($str_num)
{
   if(is_null($str_num) || strlen($str_num) < 1){
      return 0.0;       
   }
   $m_str = $str_num;
   $slen = strtrim($m_str);
   if($slen < 1){
      return 0.0;
   }
   $mm_str = strtr($m_str, array (',' => '.'));

   return floatval($mm_str);
}

function str_to_int($str_num)
{
   $buff = $str_num;
   $rval = floatval($buff);

   if(abs($rval) > INT_MAX){
      return 0;
   }
   return floor($rval);
}


function strtrim($string)
{
   if(is_null($string) || $string == ""){
      return 0;
   }   
   return strlen(strtr($string, array (" " => "", "\t" => "", "\n" => "")));
}

function cartesian_to_wgs84_ellipsoidal(&$cart, &$geod)
{
    if($cart == null || $geod == null)
    {
        return 1;
    }      

    $e_sqr = (1.0 - (1.0 - 1.0 / INV_FLAT) * (1.0 - 1.0 / INV_FLAT));
    $new_t = $cart["z_res"] * e_sqr;
    $xy_sqr = $cart["x_res"] * $cart["x_res"] + $cart["y_res"] * $cart["y_res"];

    /// iterative procedure to get the height
    $i = 0;
    do
    {
        $t = $new_t;
        $z_t = $cart["z_res"] + $t;
        $h1 = sqrt($xy_sqr + $z_t * $z_t);
        if(abs($h1) < 1.0e-30){
            return 2;
        }

        $sin_phi = $z_t/$h1;
        $den2 = 1.0 - $e_sqr * $sin_phi * $sin_phi;
        if($den2 < 0.0){
            return 3;
        }
        $den = sqrt($den2);
        if($den == 0.0){
            return 4;
        }
        $h2 = EARTH_RAD / $den;
        $new_t = $e_sqr * $sin_phi * $h2; 
        $i++;
    }
    while($i < 50 && abs($new_t - $t) > 1.0e-8); /// maximum 50 iterations

    $geod["quota"] = $h1 - $h2; /// the computed height

    if($xy_sqr < 1.0e-30)
    {
        if($cart["z_res"] > 0.0){
            $geod["latitude"] = 90.0;
        }
        else{
            $geod["latitude"] = -90.0;
        }
        $geod["longitude"] = 0.0;
    }
    else
    {
        $geod["latitude"] = 360.0 * atan($z_t / sqrt($xy_sqr)) / (2.0 * _PI); /// geodetic latitude 
        $geod["longitude"] = 360.0 * atan($cart["y_res"] / $cart["x_res"]) / (2.0 * _PI); /// east longitude
    }
    return 0;
}

function wgs84_ellipsoidal_to_cartesian(&$grad_geodetic, &$cartesian)
{
        if($grad_geodetic == null || $cartesian == null)
        {
            return 1;
        }
        
	$geodetic = array();
	$geodetic["latitude"] = _PI * $grad_geodetic["latitude"] / 180.0;
	$geodetic["longitude"] = _PI * $grad_geodetic["longitude"] / 180.0;
	$geodetic["quota"] = $grad_geodetic["quota"];

	$sq_eccen = 1.0 - (1.0 - 1.0 / INV_FLAT) * (1.0 - 1.0 / INV_FLAT); /// squared eccentricity
	$denom = sqrt(1.0 + (1.0 - $sq_eccen) * tan($geodetic["latitude"]) 
                * tan($geodetic["latitude"]));
	
	$cartesian[0] = EARTH_RAD * cos($geodetic["longitude"]) / $denom + 
            $geodetic["quota"] * cos($geodetic["latitude"]) * cos($geodetic["longitude"]);

	$cartesian[1] = EARTH_RAD * sin($geodetic["longitude"]) / $denom + 
            $geodetic["quota"] * cos($geodetic["latitude"]) * sin($geodetic["longitude"]);

	$cartesian[2] = EARTH_RAD * (1.0 - $sq_eccen) * sin($geodetic["latitude"]) / 
            sqrt(1.0 - $sq_eccen * sin($geodetic["latitude"]) * sin($geodetic["latitude"])) + 
            $geodetic["quota"] * sin($geodetic["latitude"]);
        
        return 0;
}

?>