<?php

class User {
    private $id;
    private $username;
    private $school;
    private $points;

    
    public function __construct($id, $username, $school, $points){
        $this->id = $id;
        $this->username = $username;
        $this->school = $school;
        $this->points = $points;
        
    }

    public function getId() {
        return $this->id;
    }

    public function getUsername(){
        return $this->username;
    }
    
    public function getSchool(){
        return $this->school;
    }

    public function getPoints(){
        return $this->points;
    }
    
}

?>