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
        $url  = "mysql:host=$servername;dbname=$dbname;port=$port";

        return new PDO($url, $username, $password);
    }
}

?>