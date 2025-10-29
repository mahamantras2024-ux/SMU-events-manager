<?php

class ConnectionManager
{
    public function getConnection()
    {
        $servername = 'localhost';
        $dbname = 'omni';
        $username = 'root';
        $password = '';
        $port = 3306;

        $conn  = new PDO("mysql:host=$servername;dbname=$dbname;port=$port", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // if fail, exception will be thrown

        return $conn;
    }
}

?>