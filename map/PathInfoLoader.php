<?php

if(isset($_GET["areaId"]))
{
    $areaId = $_GET["areaId"];
    if(isset($_GET["id"]))
    {
        $id = $_GET["id"];
        $filename = "./data/zone" . $areaId . "/descr/" . $id . ".txt";
        $output = explode("$", file_get_contents($filename));

        $result = (object) array("description" => utf8_encode($output[0]), "link" => utf8_encode($output[1]));

        echo json_encode($result);
    }
}
