<?php

include('lib/gnss-coordinates-transformation-handler.php');

//the path of the file containing the parameters for coordinate transformations
$path_of_transformation_params_file = "TRF_transf.TP";

//HTTP POST parameters
$type = $_POST["type"];
$start_ref_frame = $_POST["start_ref_frame"];
$x_coord = $_POST["x_coord"];
$y_coord = $_POST["y_coord"];
$z_coord = $_POST["z_coord"];
$x_vel = $_POST["x_vel"];
$y_vel = $_POST["y_vel"];
$z_vel = $_POST["z_vel"];
$start_year = $_POST["start_year"];
$start_doy = sprintf("%03d", $_POST["start_doy"]);
$start_sod = $_POST["start_sod"];
$end_ref_frame = $_POST["end_ref_frame"];
$end_year = $_POST["end_year"];
$end_doy = sprintf("%03d", $_POST["end_doy"]);
$end_sod = $_POST["end_sod"];

$end_x_coord = "";
$end_y_coord = "";
$end_z_coord = "";

//turn geodetic coords into cartesian
if($type == "geodetic")
{
    $converted = array(0.0,0.0,0.0);
    $geod = array("longitude" => $x_coord, "latitude" => $y_coord, "quota" => $z_coord);
    wgs84_ellipsoidal_to_cartesian($geod, $converted);
    $x = $converted[0];
    $y = $converted[1];
    $z = $converted[2];
}
else
{
    $x = $x_coord;
    $y = $y_coord;
    $z = $z_coord;
}

mng_coordinates_transformation($path_of_transformation_params_file,
        $start_ref_frame, $end_ref_frame, $x, $y, $z, 
        $x_vel, $y_vel, $z_vel, $start_year, $start_doy, $start_sod,
        $end_year, $end_doy, $end_sod, $end_x_coord, $end_y_coord, $end_z_coord);

//get strings from result coordinates, so to avoid rounding
$cartesian = array();
$rx = number_format($end_x_coord,7,'.','');
$ry = number_format($end_y_coord,7,'.','');
$rz = number_format($end_z_coord,7,'.','');
$cartesian["x_res"] = substr($rx,-strlen($rx),-2);
$cartesian["y_res"] = substr($ry,-strlen($ry),-2);
$cartesian["z_res"] = substr($rz,-strlen($rz),-2);

//compute result ellipsoidal coordinates
if($type == "cartesian")
{
    $ellipsoidal = array("latitude" => 0.0, "longitude" => 0.0, "quota" => 0.0);
    cartesian_to_wgs84_ellipsoidal($cartesian,$ellipsoidal);
}
else
{
    $ellipsoidal = array("longitude" => $x_coord, "latitude" => $y_coord, "quota" => $z_coord);
}

//return JSON object from labeled array [x_res, y_res, z_res]
header('Content-Type: application/json');
print json_encode(array("cartesian" => $cartesian, 
                        "ellipsoidal" => $ellipsoidal));
?>
