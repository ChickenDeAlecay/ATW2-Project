<?php

$host = "localhost";
$database = "bristol_trees";
$username = "root";
$password = "";

$connect = mysqli_connect($host, $username, $password, $database);

if (!$connect) {
    die("Connection failed: " . mysqli_connect_error());
}