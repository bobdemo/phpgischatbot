<?php

if(isset($_GET['type']))
{
    $type = $_GET['type'];
    $page = null;
    if($type == 'mediaManagement')
    {
        $page = json_encode(file_get_contents("mediaView.html"));
    }
    echo $page;
}