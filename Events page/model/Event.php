<?php

class Event {
    private $id;
    private $title;
    private $category;
    private $date;
    private $start_time;
    private $end_time;
    private $location;
    private $picture;
    private $startISO;
    private $endISO;


    
    public function __construct($id, $title, $category, $date, $start_time, $end_time, $location, $picture, $startISO, $endISO){
        $this->id = $id;
        $this->title = $title;
        $this->category = $category;
        $this->date = $date;
        $this->start_time = $start_time;
        $this->end_time = $end_time;
        $this->location = $location;
        $this->picture = $picture;
        $this->startISO = $startISO;
        $this->endISO = $endISO;
    }

    public function getId() {
        return $this->id;
    }

    public function getTitle(){
        return $this->title;
    }
    
    public function getCategory(){
        return $this->category;
    }

    public function getDate(){
        return $this->date;
    }

    public function getStartTime(){
        return $this->start_time;
    }

    public function getEndTime(){
        return $this->end_time;
    }

    public function getLocation(){
        return $this->location;
    }

    public function getPicture(){
        return $this->picture;
    }
    
    public function getStartISO(){
        return $this->startISO;
    }

    public function getEndISO(){
        return $this->endISO;
    }
    
}

?>