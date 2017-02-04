<?php

$reference_frames = array("IGb00","IGb08","IGS97","IGS00","IGS05","IGS08",
    "ITRF88","ITRF89","ITRF90","ITRF91","ITRF92","ITRF93","ITRF94","ITRF96",
    "ITRF97","ITRF00","ITRF05");

header('Content-Type: application/json');
print json_encode($reference_frames);

?>
