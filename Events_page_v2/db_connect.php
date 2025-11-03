<?php
class ConnectionManager {
    public function connect() {
        $servername = 'localhost';
        $username = 'root';
        $password = '';
        $dbname = 'omni';
        $port = '3306';

        try {
            $pdo = new PDO(
                "mysql:host=$servername;dbname=$dbname;port=$port",
                $username,
                $password
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
            return null;
        }
    }
}
?>