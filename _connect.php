<?php

$host = "localhost:3306";
$database = "WS371518_ATW2";
$username = "WS371518_ATW2";
$password = "qZ00f3*e7kR61#d56p";

$connect = mysqli_connect($host, $username, $password, $database);

if (!$connect) {
    die("Connection failed: " . mysqli_connect_error());
}